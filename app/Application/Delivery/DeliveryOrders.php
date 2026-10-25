<?php

namespace App\Application\Delivery;

use App\Application\Cash\CashOperations;
use App\Application\Orders\OrderFulfillment;
use App\Application\Orders\OrderPricing;
use App\Enums\StaffPermission;
use App\Models\CashShift;
use App\Models\DeliveryAccount;
use App\Models\DeliveryIntent;
use App\Models\DiningOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryOrders
{
    public function __construct(private OrderPricing $pricing, private OrderFulfillment $fulfillment, private DeliveryCoverage $coverage) {}

    /** @param array<string, mixed> $input */
    public function preview(array $input, DeliveryContext $context): DeliveryIntent
    {
        $data = Validator::make($input, ['phone' => 'required|string|max:40', 'address' => 'required|array',
            'items' => 'required|array|min:1|max:20', 'items.*' => 'required|array:variant_id,second_variant_id,quantity,options,manual_discount,note',
            'items.*.note' => 'nullable|string|max:500', 'delivery_fee' => 'prohibited', 'scheduled_at' => 'prohibited'])->validate();
        $phone = DeliveryInput::phone($data['phone']);
        $address = DeliveryInput::address($data['address']);

        return DB::transaction(function () use ($data, $phone, $address, $context): DeliveryIntent {
            $context->authorize(StaffPermission::PlaceDeliveryOrder);
            $coverage = $this->coverage->match($address);
            $snapshot = $this->pricing->quoteItems($data['items'], $context);
            $items = (int) array_sum(array_column($snapshot, 'total_cents'));
            DeliveryCoverage::minimum($items, $coverage['minimum_cents']);
            $this->limit($items + $coverage['fee_cents']);
            $intent = new DeliveryIntent;
            $intent->forceFill(['id' => (string) Str::uuid(), 'actor_id' => $context->actorId, 'auth_version' => $context->authVersion,
                'client_hash' => $context->grant?->clientHash(), 'phone' => $phone, 'address' => $address, 'coverage' => $coverage,
                'request' => ['items' => $data['items']], 'snapshot' => $snapshot, 'total_cents' => $items + $coverage['fee_cents'],
                'created_at' => now(), 'expires_at' => now()->addMinutes(10)])->save();

            return $intent;
        });
    }

    public function intent(string $id, DeliveryContext $context): DeliveryIntent
    {
        $context->authorize(StaffPermission::PlaceDeliveryOrder);

        return DeliveryIntent::query()->where('actor_id', $context->actorId)->where('auth_version', $context->authVersion)
            ->where('client_hash', $context->grant?->clientHash())->findOrFail($id);
    }

    public function confirm(string $id, DeliveryContext $context): DiningOrder
    {
        return DB::transaction(function () use ($id, $context): DiningOrder {
            CashOperations::lock();
            DB::table('delivery_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            $intent = $this->intent($id, $context);
            $existing = DiningOrder::query()->where('delivery_intent_id', $id)->first();
            if ($existing) {
                return $existing;
            }
            if ($intent->expires_at <= now()) {
                throw ValidationException::withMessages(['basket' => 'A prévia expirou. Confira uma nova seleção.']);
            }
            if ($context->grant && DiningOrder::query()->where('modality', 'delivery')->where('client_hash', $context->grant->clientHash())
                ->where('created_at', '>', now()->subMinute())->count() >= 3) {
                throw ValidationException::withMessages(['basket' => 'Muitos pedidos em pouco tempo. Aguarde um minuto.']);
            }
            $coverage = $this->coverage->match($intent->address);
            $previousCoverage = $intent->coverage;
            ksort($coverage);
            ksort($previousCoverage);
            $snapshot = $this->pricing->quoteItems($intent->request['items'], $context);
            if ($coverage !== $previousCoverage || $this->pricing->canonical($snapshot) !== $this->pricing->canonical($intent->snapshot)) {
                throw ValidationException::withMessages(['basket' => 'Preço, itens ou cobertura mudaram. Faça uma nova prévia.']);
            }
            $items = (int) array_sum(array_column($snapshot, 'total_cents'));
            DeliveryCoverage::minimum($items, $coverage['minimum_cents']);
            $this->limit($items + $coverage['fee_cents']);
            $account = new DeliveryAccount;
            $account->forceFill(['phone' => $intent->phone, 'address' => $intent->address, 'coverage' => $coverage,
                'fee_cents' => $coverage['fee_cents'], 'minimum_cents' => $coverage['minimum_cents'], 'opened_at' => now()])->save();
            $order = new DiningOrder;
            $order->forceFill(['public_id' => (string) Str::uuid(), 'delivery_intent_id' => $id, 'delivery_account_id' => $account->id,
                'modality' => 'delivery', 'source' => $context->source(), 'actor_id' => $context->actorId, 'client_hash' => $context->grant?->clientHash(),
                'cash_shift_id' => CashShift::query()->whereNull('closed_at')->value('id'), 'status' => 'confirmed', 'currency' => 'BRL',
                'total_cents' => $items, 'created_at' => now()])->save();
            foreach ($snapshot as $position => $item) {
                $order->items()->make()->forceFill(['position' => $position, 'product_id' => $item['product_id'], 'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'], 'note' => $item['note'], 'snapshot' => $item, 'total_cents' => $item['total_cents']])->save();
            }
            $this->fulfillment->created($order, $context);

            return $order;
        });
    }

    private function limit(int $total): void
    {
        if ($total > 100000000) {
            throw ValidationException::withMessages(['basket' => 'O pedido excede o limite de registro. Revise os itens.']);
        }
    }
}
