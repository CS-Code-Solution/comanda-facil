<?php

namespace App\Application\Pickup;

use App\Application\Cash\CashOperations;
use App\Application\Orders\OperationsChanges;
use App\Application\Orders\OrderFulfillment;
use App\Application\Orders\OrderPricing;
use App\Enums\StaffPermission;
use App\Models\CashShift;
use App\Models\DiningOrder;
use App\Models\PickupAccount;
use App\Models\PickupIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PickupOrders
{
    public function __construct(private OrderPricing $pricing, private OrderFulfillment $fulfillment) {}

    public function service(bool $open, string $reason, PickupContext $context): void
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:3|max:500'])->validate();
        DB::transaction(function () use ($open, $reason, $context): void {
            DB::table('pickup_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            $context->authorize(StaffPermission::ManagePickup);
            DB::table('pickup_settings')->where('id', 1)->update(['accepting_orders' => $open]);
            DB::table('pickup_events')->insert(['actor_id' => $context->actorId, 'action' => 'receiving_changed',
                'details' => json_encode(['open' => $open, 'reason' => $reason], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            OperationsChanges::record();
        });
    }

    /** @param array<string, mixed> $input */
    public function preview(array $input, PickupContext $context): PickupIntent
    {
        $data = Validator::make($input, ['phone' => 'required|string|max:40', 'items' => 'required|array|min:1|max:20',
            'items.*' => 'required|array:variant_id,second_variant_id,quantity,options,manual_discount,note', 'items.*.note' => 'nullable|string|max:500',
            'delivery_fee' => 'prohibited', 'address' => 'prohibited', 'scheduled_at' => 'prohibited'])->validate();
        $phone = $this->phone($data['phone']);

        return DB::transaction(function () use ($data, $phone, $context): PickupIntent {
            $context->authorize(StaffPermission::PlacePickupOrder);
            $this->receiving();
            $snapshot = $this->pricing->quoteItems($data['items'], $context);
            $intent = new PickupIntent;
            $intent->forceFill(['id' => (string) Str::uuid(), 'actor_id' => $context->actorId, 'auth_version' => $context->authVersion,
                'client_hash' => $context->grant?->clientHash(), 'phone' => $phone, 'request' => ['items' => $data['items']],
                'snapshot' => $snapshot, 'total_cents' => array_sum(array_column($snapshot, 'total_cents')), 'created_at' => now(), 'expires_at' => now()->addMinutes(10)])->save();

            return $intent;
        });
    }

    public function intent(string $id, PickupContext $context): PickupIntent
    {
        $context->authorize(StaffPermission::PlacePickupOrder);
        $intent = PickupIntent::query()->where('actor_id', $context->actorId)->where('auth_version', $context->authVersion)
            ->where('client_hash', $context->grant?->clientHash())->findOrFail($id);

        return $intent;
    }

    public function confirm(string $id, PickupContext $context): DiningOrder
    {
        return DB::transaction(function () use ($id, $context): DiningOrder {
            CashOperations::lock();
            DB::table('pickup_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            $intent = $this->intent($id, $context);
            $existing = DiningOrder::query()->where('pickup_intent_id', $id)->first();
            if ($existing) {
                return $existing;
            }
            $this->receiving();
            if ($intent->expires_at <= now()) {
                throw ValidationException::withMessages(['basket' => 'A prévia expirou. Confira uma nova seleção.']);
            }
            if ($context->grant && DiningOrder::query()->where('modality', 'pickup')->where('client_hash', $context->grant->clientHash())
                ->where('created_at', '>', now()->subMinute())->count() >= 3) {
                throw ValidationException::withMessages(['basket' => 'Muitos pedidos em pouco tempo. Aguarde um minuto.']);
            }
            $snapshot = $this->pricing->quoteItems($intent->request['items'], $context);
            if ($this->pricing->canonical($snapshot) !== $this->pricing->canonical($intent->snapshot)) {
                throw ValidationException::withMessages(['basket' => 'Preço ou configuração mudou. Faça uma nova prévia.']);
            }
            $account = new PickupAccount;
            $account->forceFill(['phone' => $intent->phone, 'opened_at' => now()])->save();
            $order = new DiningOrder;
            $order->forceFill(['public_id' => (string) Str::uuid(), 'pickup_intent_id' => $id, 'pickup_account_id' => $account->id,
                'modality' => 'pickup', 'source' => $context->source(), 'actor_id' => $context->actorId, 'client_hash' => $context->grant?->clientHash(),
                'cash_shift_id' => CashShift::query()->whereNull('closed_at')->value('id'), 'status' => 'confirmed', 'currency' => 'BRL',
                'total_cents' => array_sum(array_column($snapshot, 'total_cents')), 'created_at' => now()])->save();
            foreach ($snapshot as $position => $item) {
                $order->items()->make()->forceFill(['position' => $position, 'product_id' => $item['product_id'], 'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'], 'note' => $item['note'], 'snapshot' => $item, 'total_cents' => $item['total_cents']])->save();
            }
            $this->fulfillment->created($order, $context);

            return $order;
        });
    }

    private function receiving(): void
    {
        $settings = DB::table('pickup_settings')->where('id', 1)->sharedLock()->firstOrFail();
        if (! $settings->accepting_orders) {
            throw ValidationException::withMessages(['basket' => 'O recebimento de retirada está pausado. Procure o restaurante.']);
        }
    }

    private function phone(string $input): string
    {
        $digits = preg_replace('/[\s().+-]/u', '', $input);
        if (is_string($digits) && in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }
        if (! is_string($digits) || ! preg_match('/^[1-9][0-9](?:[2-8][0-9]{7}|9[0-9]{8})$/', $digits)) {
            throw ValidationException::withMessages(['phone' => 'Informe telefone brasileiro com DDD e 8 ou 9 dígitos, com +55 opcional.']);
        }

        return '+55'.$digits;
    }
}
