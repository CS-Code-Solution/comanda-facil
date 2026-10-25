<?php

namespace App\Application\Orders;

use App\Application\Dining\DiningContext;
use App\Enums\StaffPermission;
use App\Models\DeliveryAccount;
use App\Models\DiningOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use stdClass;

class OrderPrinting
{
    /** @param array<string, mixed> $before */
    public function enqueue(DiningOrder $order, string $kind, array $before = [], ?int $actorId = null): void
    {
        DB::table('print_jobs')->where('order_id', $order->id)->where('revision', '<', $order->content_revision)
            ->whereIn('status', ['pending', 'printing', 'failed'])->update(['status' => 'superseded']);
        $items = $order->items()->get()->map(fn ($item) => $item->snapshot)->all();
        foreach (['kitchen', 'cashier'] as $target) {
            // Remove commercial data from kitchen snapshots, including previous revisions.
            $safeItems = fn (array $rows): array => array_map(fn (array $item): array => $target === 'cashier' ? $item : array_intersect_key($item,
                array_flip(['name', 'variant_name', 'quantity', 'note', 'options', 'composition', 'flavors'])), $rows);
            $stripPrices = function (array $data) use (&$stripPrices): array {
                foreach ($data as $key => $value) {
                    if (is_string($key) && (str_contains($key, 'cents') || str_contains($key, 'label') || $key === 'applied_discount')) {
                        unset($data[$key]);
                    } elseif (is_array($value)) {
                        $data[$key] = $stripPrices($value);
                    }
                }

                return $data;
            };
            $snapshot = ['reference' => $order->public_id, 'table' => $order->table_number, 'locationLabel' => $order->locationLabel(), 'modality' => $order->modality, 'status' => $order->status,
                'revision' => $order->content_revision, 'items' => $safeItems($items), 'previousItems' => $safeItems($before['items'] ?? []),
                'total_cents' => $order->total_cents, 'created_at' => now()->toIso8601String()];
            if ($target === 'kitchen') {
                $snapshot = $stripPrices($snapshot);
            } elseif ($order->modality === 'delivery') {
                $fee = DeliveryAccount::query()->findOrFail($order->delivery_account_id)->fee_cents;
                $snapshot['delivery_fee_cents'] = $fee;
                $snapshot['total_cents'] = $order->total_cents + $fee;
            }
            if (! DB::table('print_jobs')->where('order_id', $order->id)->where('revision', $order->content_revision)->where('target', $target)->exists()) {
                $id = DB::table('print_jobs')->insertGetId(['order_id' => $order->id, 'revision' => $order->content_revision,
                    'target' => $target, 'kind' => $kind, 'copy' => 1, 'status' => 'pending', 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => now()]);
                $this->audit($id, $actorId, 'created');
            }
        }
    }

    public function legacy(int $id, DiningContext $context): void
    {
        DB::transaction(function () use ($id, $context): void {
            $context->authorize(StaffPermission::PrintCashier);
            $order = DiningOrder::query()->lockForUpdate()->findOrFail($id);
            $this->enqueue($order, $order->status === 'cancelled' ? 'cancellation' : 'original', actorId: $context->actorId);
            OperationsChanges::record($order->public_id);
        });
    }

    public function authorizedJob(int $id, DiningContext $context): stdClass
    {
        $job = DB::table('print_jobs')->where('id', $id)->first();
        abort_unless($job !== null, 404);
        $context->authorize($job->target === 'kitchen' ? StaffPermission::PrintKitchen : StaffPermission::PrintCashier);

        return $job;
    }

    /** @param array<string, mixed> $input */
    public function act(int $id, string $action, array $input, DiningContext $context): int
    {
        $data = Validator::make($input, ['reason' => ($action === 'failed' || $action === 'reprint' ? 'required' : 'nullable').'|string|min:3|max:500',
            'operation_id' => ($action === 'reprint' ? 'required' : 'nullable').'|uuid'])->validate();

        return DB::transaction(function () use ($id, $action, $data, $context): int {
            $initial = $this->authorizedJob($id, $context);
            $order = DiningOrder::query()->lockForUpdate()->where('id', $initial->order_id)->firstOrFail();
            $job = DB::table('print_jobs')->where('id', $id)->lockForUpdate()->first();
            abort_unless($job !== null, 404);
            if ($job->revision !== $order->content_revision || $job->status === 'superseded') {
                throw ValidationException::withMessages(['printing' => 'Comprovante de revisão antiga. Abra a fila atualizada.']);
            }
            if ($action === 'reprint') {
                $existing = DB::table('print_jobs')->where('request_id', $data['operation_id'])->first();
                if ($existing) {
                    abort_unless($existing->origin_job_id === $id && $existing->requested_by_id === $context->actorId && $existing->reason === $data['reason'], 403);

                    return $existing->id;
                }
                if ($job->status === 'pending' || ($job->status === 'printing' && now()->lessThan(CarbonImmutable::parse($job->claimed_at)->addMinutes(2)))) {
                    throw ValidationException::withMessages(['printing' => 'Há uma impressão pendente ou em andamento. Aguarde ou registre a falha.']);
                }
                $latestCopy = (int) DB::table('print_jobs')->where('order_id', $order->id)->where('revision', $job->revision)->where('target', $job->target)->max('copy');
                if ($job->copy !== $latestCopy) {
                    throw ValidationException::withMessages(['printing' => 'Já existe uma cópia mais recente. Abra o último trabalho na fila antes de repetir.']);
                }
                $copy = $latestCopy + 1;
                if ($job->status === 'printing') {
                    DB::table('print_jobs')->where('id', $id)->update(['status' => 'failed', 'completed_at' => now()]);
                    $this->audit($id, $context->actorId, 'lease_expired', $data['reason']);
                }
                $newId = DB::table('print_jobs')->insertGetId(['order_id' => $order->id, 'revision' => $job->revision, 'target' => $job->target,
                    'kind' => $job->kind, 'copy' => $copy, 'snapshot' => $job->snapshot, 'status' => 'pending', 'created_at' => now(),
                    'request_id' => $data['operation_id'], 'origin_job_id' => $id, 'requested_by_id' => $context->actorId, 'reason' => $data['reason']]);
                $this->audit($newId, $context->actorId, 'reprint', $data['reason']);
                OperationsChanges::record($order->public_id);

                return $newId;
            }
            if ($action === 'claim') {
                if ($job->status === 'printing' && $job->claimed_by_id === $context->actorId) {
                    return $id;
                }
                if ($job->status !== 'pending') {
                    throw ValidationException::withMessages(['printing' => 'Este trabalho já foi assumido. Recarregue a fila.']);
                }
                DB::table('print_jobs')->where('id', $id)->update(['status' => 'printing', 'claimed_by_id' => $context->actorId, 'claimed_at' => now()]);
            } else {
                abort_unless(in_array($action, ['printed', 'failed'], true), 404);
                if ($job->status === $action && $job->claimed_by_id === $context->actorId) {
                    return $id;
                }
                if ($job->status !== 'printing' || $job->claimed_by_id !== $context->actorId) {
                    throw ValidationException::withMessages(['printing' => 'Somente quem assumiu esta impressão pode registrar o resultado.']);
                }
                DB::table('print_jobs')->where('id', $id)->update(['status' => $action, 'completed_at' => now()]);
            }
            $this->audit($id, $context->actorId, $action, $data['reason'] ?? null);
            OperationsChanges::record($order->public_id);

            return $id;
        });
    }

    private function audit(int $id, ?int $actor, string $action, ?string $reason = null): void
    {
        DB::table('print_events')->insert(['job_id' => $id, 'actor_id' => $actor, 'action' => $action, 'reason' => $reason, 'created_at' => now()]);
    }
}
