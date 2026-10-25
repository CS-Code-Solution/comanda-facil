<?php

namespace App\Application\Dining;

use App\Application\Cash\CashOperations;
use App\Application\Orders\OrderFulfillment;
use App\Application\Orders\OrderPricing;
use App\Enums\StaffPermission;
use App\Models\CashShift;
use App\Models\DiningAlert;
use App\Models\DiningOrder;
use App\Models\DiningOrderIntent;
use App\Models\DiningSession;
use App\Models\DiningTable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DiningOrders
{
    public function __construct(private OrderPricing $pricing) {}

    /** @param array<string, mixed> $input */
    public function preview(int $tableId, array $input, DiningContext $context): DiningOrderIntent
    {
        return DB::transaction(function () use ($tableId, $input, $context): DiningOrderIntent {
            $table = DiningAccess::lockTable($tableId);
            $context->authorize(StaffPermission::PlaceDiningOrder, $table, true);
            DiningAccess::receiving($table);
            $data = Validator::make($input, [
                'cycle' => 'required|integer|min:0', 'items' => 'required|array|min:1|max:20',
                'items.*' => 'required|array:variant_id,second_variant_id,quantity,options,manual_discount,note',
                'items.*.note' => 'nullable|string|max:500',
            ])->validate();
            if ((int) $data['cycle'] !== $table->cycle) {
                throw ValidationException::withMessages(['basket' => 'O atendimento mudou. Reabra a mesa e revise o pedido.']);
            }
            $this->unsettled($table);
            $items = [];
            foreach ($data['items'] as $item) {
                $options = $item['options'] ?? [];
                if (is_array($options)) {
                    ksort($options);
                }
                $items[] = [...$item, 'options' => $options];
            }
            $snapshot = $this->pricing->quoteItems($items, $context);
            $intent = new DiningOrderIntent;
            $intent->forceFill(['id' => (string) Str::uuid(), 'table_id' => $tableId, 'cycle' => $table->cycle,
                'actor_id' => $context->actorId, 'client_hash' => $context->grant?->clientHash(), 'source' => $context->source(),
                'request' => ['items' => $items], 'snapshot' => $snapshot, 'total_cents' => array_sum(array_column($snapshot, 'total_cents')),
                'created_at' => now(), 'expires_at' => now()->addMinutes((int) config('dining.intent_minutes'))])->save();

            return $intent;
        });
    }

    public function intent(string $id, int $tableId, DiningContext $context): DiningOrderIntent
    {
        $context->authorize(StaffPermission::PlaceDiningOrder);
        $intent = DiningOrderIntent::query()->where('table_id', $tableId)->findOrFail($id);
        $this->owns($intent, $context);

        return $intent;
    }

    public function confirm(string $id, int $tableId, DiningContext $context): DiningOrder
    {
        return DB::transaction(function () use ($id, $tableId, $context): DiningOrder {
            CashOperations::lock();
            $table = DiningAccess::lockTable($tableId);
            $context->authorize(StaffPermission::PlaceDiningOrder, $table);
            $intent = DiningOrderIntent::query()->where('table_id', $tableId)->lockForUpdate()->findOrFail($id);
            $this->owns($intent, $context);
            $existing = DiningOrder::query()->where('intent_id', $id)->first();
            if ($existing) {
                return $existing;
            }
            $context->authorize(StaffPermission::PlaceDiningOrder, $table, true);
            DiningAccess::receiving($table);
            if ($intent->expires_at <= now() || $intent->cycle !== $table->cycle) {
                throw ValidationException::withMessages(['basket' => 'A confirmação expirou ou o atendimento mudou. Faça uma nova prévia.']);
            }
            $this->unsettled($table);
            $snapshot = $this->pricing->quoteItems($intent->request['items'], $context);
            if ($this->pricing->canonical($snapshot) !== $this->pricing->canonical($intent->snapshot)) {
                throw ValidationException::withMessages(['basket' => 'O preço ou a configuração mudou. Revise uma nova prévia antes de confirmar.']);
            }
            if ($context->grant) {
                $recent = DiningOrder::query()->where('table_id', $tableId)->where('source', 'qr')->where('created_at', '>', now()->subMinute());
                if ((clone $recent)->count() >= config('dining.table_orders_per_minute') || $recent->where('client_hash', $context->grant->clientHash())->count() >= config('dining.browser_orders_per_minute')) {
                    throw ValidationException::withMessages(['basket' => 'Muitos pedidos em pouco tempo. Aguarde um minuto ou chame a equipe.']);
                }
            }
            $session = DiningSession::query()->where('table_id', $tableId)->whereNull('closed_at')->first();
            if (! $session) {
                $session = new DiningSession;
                $session->forceFill(['table_id' => $tableId, 'cycle' => $table->cycle, 'opened_at' => now(), 'opened_by_id' => $context->actorId])->save();
                DiningAudit::record('session_opened', $context, $tableId, ['session_id' => $session->id, 'cycle' => $table->cycle]);
                if ($table->cycle > 0) {
                    (new DiningAlert)->forceFill(['table_id' => $tableId, 'session_id' => $session->id, 'type' => 'session_reopened', 'created_at' => now()])->save();
                    DiningAudit::record('session_reopened', $context, $tableId, ['session_id' => $session->id]);
                }
            }
            $order = new DiningOrder;
            $order->forceFill(['public_id' => (string) Str::uuid(), 'intent_id' => $intent->id, 'table_id' => $tableId, 'session_id' => $session->id,
                'cash_shift_id' => CashShift::query()->whereNull('closed_at')->value('id'), 'table_number' => $table->number, 'modality' => 'dining_room', 'source' => $context->source(), 'actor_id' => $context->actorId,
                'client_hash' => $context->grant?->clientHash(), 'status' => 'confirmed', 'currency' => 'BRL',
                'total_cents' => array_sum(array_column($snapshot, 'total_cents')), 'created_at' => now()])->save();
            foreach ($snapshot as $position => $item) {
                $order->items()->make()->forceFill(['order_id' => $order->id, 'position' => $position, 'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'], 'quantity' => $item['quantity'], 'note' => $item['note'], 'snapshot' => $item, 'total_cents' => $item['total_cents']])->save();
            }
            DiningAudit::record('order_confirmed', $context, $tableId, ['order_id' => $order->id, 'session_id' => $session->id, 'source' => $order->source, 'status' => $order->status, 'total_cents' => $order->total_cents]);

            app(OrderFulfillment::class)->created($order, $context);

            return $order;
        });
    }

    private function unsettled(DiningTable $table): void
    {
        if (DiningSession::query()->where('table_id', $table->id)->whereNull('closed_at')->whereNotNull('settled_at')->exists()) {
            throw ValidationException::withMessages(['basket' => 'A conta foi quitada. Peça à equipe para encerrar este atendimento.']);
        }
    }

    private function owns(DiningOrderIntent $intent, DiningContext $context): void
    {
        if ($intent->source !== $context->source() || $intent->actor_id !== $context->actorId || $intent->client_hash !== $context->grant?->clientHash()) {
            throw (new ModelNotFoundException)->setModel(DiningOrderIntent::class);
        }
    }
}
