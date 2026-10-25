<?php

namespace App\Application\Dining;

use App\Application\Orders\OperationsChanges;
use App\Enums\StaffPermission;
use App\Models\DiningAlert;
use App\Models\DiningCall;
use App\Models\DiningSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiningCalls
{
    public function request(GuestGrant $grant): DiningCall
    {
        return DB::transaction(function () use ($grant): DiningCall {
            $table = DiningAccess::lockTable($grant->tableId);
            $grant->authorize($table, true);
            DiningAccess::receiving($table);
            $existing = DiningCall::query()->where('table_id', $table->id)->where('cycle', $table->cycle)->whereIn('status', ['pending', 'claimed'])->first();
            if ($existing) {
                return $existing;
            }
            if (DiningCall::query()->where('table_id', $table->id)->where('client_hash', $grant->clientHash())->where('created_at', '>', now()->subSeconds((int) config('dining.call_cooldown_seconds')))->exists()) {
                throw ValidationException::withMessages(['call' => 'Aguarde antes de abrir outro chamado.']);
            }
            $call = new DiningCall;
            $call->forceFill(['table_id' => $table->id, 'cycle' => $table->cycle, 'session_id' => DiningSession::query()->where('table_id', $table->id)->whereNull('closed_at')->value('id'),
                'client_hash' => $grant->clientHash(), 'status' => 'pending', 'created_at' => now()])->save();
            OperationsChanges::record();
            DiningAudit::record('call_requested', DiningContext::guest($grant), $table->id, ['call_id' => $call->id]);

            return $call;
        });
    }

    public function respond(int $id, bool $complete, DiningContext $context): void
    {
        DB::transaction(function () use ($id, $complete, $context) {
            $tableId = DiningCall::query()->findOrFail($id)->table_id;
            DiningAccess::lockTable($tableId);
            $context->authorize(StaffPermission::AttendDiningCalls);
            $call = DiningCall::query()->lockForUpdate()->findOrFail($id);
            if ($call->status === 'completed') {
                return;
            }
            if (! $complete && $call->status === 'pending') {
                $call->forceFill(['status' => 'claimed', 'claimed_by_id' => $context->actorId, 'claimed_at' => now()])->save();
            } elseif ($complete && $call->status === 'claimed' && $call->claimed_by_id === $context->actorId) {
                $call->forceFill(['status' => 'completed', 'completed_by_id' => $context->actorId, 'completed_at' => now()])->save();
            } else {
                throw ValidationException::withMessages(['call' => 'O chamado deve ser assumido e concluído pelo mesmo atendente.']);
            }
            OperationsChanges::record();
            DiningAudit::record($complete ? 'call_completed' : 'call_claimed', $context, $tableId, ['call_id' => $id]);
        });
    }

    public function acknowledge(int $id, DiningContext $context): void
    {
        DB::transaction(function () use ($id, $context) {
            $tableId = DiningAlert::query()->findOrFail($id)->table_id;
            DiningAccess::lockTable($tableId);
            $context->authorize(StaffPermission::DiningRoom);
            $alert = DiningAlert::query()->lockForUpdate()->findOrFail($id);
            if ($alert->acknowledged_at !== null) {
                return;
            }
            $alert->forceFill(['acknowledged_at' => now(), 'acknowledged_by_id' => $context->actorId])->save();
            OperationsChanges::record();
            DiningAudit::record('alert_acknowledged', $context, $tableId, ['alert_id' => $id]);
        });
    }
}
