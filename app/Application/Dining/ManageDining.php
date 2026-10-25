<?php

namespace App\Application\Dining;

use App\Application\Cash\AccountBalance;
use App\Enums\StaffPermission;
use App\Models\DiningSession;
use App\Models\DiningTable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManageDining
{
    /** @param array<string, mixed> $input */
    public function createTable(array $input, DiningContext $context): DiningTable
    {
        $data = Validator::make($input, ['number' => 'required|integer|between:1,9999', 'label' => 'nullable|string|max:80'])->validate();
        try {
            return DB::transaction(function () use ($data, $context): DiningTable {
                $context->authorize(StaffPermission::ManageDining);
                $token = bin2hex(random_bytes(32));
                $table = new DiningTable;
                $table->forceFill(['number' => (int) $data['number'], 'label' => $data['label'] ?? null,
                    'qr_token' => $token, 'qr_hash' => hash('sha256', $token)])->save();
                DiningAudit::record('table_created', $context, $table->id, ['number' => $table->number]);

                return $table;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['number' => 'Essa mesa já está cadastrada.']);
        }
    }

    /** @param array<string, mixed> $input */
    public function changeTable(int $id, array $input, DiningContext $context, bool $rotate = false): void
    {
        $data = Validator::make($input, ['label' => 'nullable|string|max:80', 'is_active' => 'required|boolean', 'is_blocked' => 'required|boolean', 'reason' => 'required|string|min:3|max:500'])->validate();
        DB::transaction(function () use ($id, $data, $context, $rotate) {
            $table = DiningAccess::lockTable($id);
            $context->authorize(StaffPermission::ManageDining);
            $before = $table->only(['label', 'is_active', 'is_blocked', 'qr_version']);
            $table->forceFill(['label' => $data['label'] ?? null, 'is_active' => $data['is_active'], 'is_blocked' => $data['is_blocked']]);
            if ($rotate) {
                $token = bin2hex(random_bytes(32));
                $table->forceFill(['qr_token' => $token, 'qr_hash' => hash('sha256', $token), 'qr_version' => $table->qr_version + 1]);
            }
            $table->save();
            DiningAudit::record($rotate ? 'qr_rotated' : 'table_changed', $context, $table->id,
                ['before' => $before, 'after' => $table->only(['label', 'is_active', 'is_blocked', 'qr_version']), 'reason' => $data['reason']]);
        });
    }

    public function service(bool $open, string $reason, DiningContext $context): void
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:3|max:500'])->validate();
        DB::transaction(function () use ($open, $reason, $context) {
            $settings = DB::table('dining_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            $context->authorize(StaffPermission::ManageDining);
            DB::table('dining_settings')->where('id', 1)->update(['accepting_orders' => $open]);
            DiningAudit::record('service_changed', $context, null, ['before' => $settings->accepting_orders, 'after' => $open, 'reason' => $reason]);
        });
    }

    public function close(int $tableId, int $sessionId, DiningContext $context): void
    {
        DB::transaction(function () use ($tableId, $sessionId, $context) {
            $table = DiningAccess::lockTable($tableId);
            $context->authorize(StaffPermission::CloseDiningSession);
            $session = DiningSession::query()->where('table_id', $tableId)->findOrFail($sessionId);
            if ($session->closed_at !== null) {
                return;
            }
            if (app(AccountBalance::class)->forSession($session)['refund_due_cents'] > 0) {
                throw ValidationException::withMessages(['close' => 'Há devolução pendente. O gestor precisa registrar o estorno antes de encerrar.']);
            }
            if ($session->settled_at === null && (int) $session->orders()->where('status', '!=', 'cancelled')->sum('total_cents') > 0) {
                throw ValidationException::withMessages(['close' => 'A conta precisa estar quitada antes de encerrar.']);
            }
            $session->forceFill(['closed_at' => now(), 'closed_by_id' => $context->actorId])->save();
            DB::table('dining_calls')->where('table_id', $tableId)->where('cycle', $session->cycle)->whereIn('status', ['pending', 'claimed'])
                ->update(['status' => 'completed', 'completed_at' => now(), 'completed_by_id' => $context->actorId]);
            $table->forceFill(['cycle' => $table->cycle + 1])->save();
            DiningAudit::record('session_closed', $context, $tableId, ['session_id' => $sessionId, 'cycle' => $session->cycle, 'calls_closed_with_session' => true]);
        });
    }
}
