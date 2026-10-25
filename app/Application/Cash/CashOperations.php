<?php

namespace App\Application\Cash;

use App\Application\Dining\DiningContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashOperations
{
    /** Acquire before table/order locks. Serializes the single restaurant cash ledger. */
    public static function lock(): void
    {
        DB::select('select pg_advisory_xact_lock(?)', [2026102407]);
    }

    /** @param array<string, mixed> $data */
    public static function fingerprint(string $kind, array $data): string
    {
        return hash('sha256', json_encode([$kind, $data], JSON_THROW_ON_ERROR));
    }

    public static function replay(string $id, string $fingerprint, DiningContext $context): ?int
    {
        $operation = DB::table('cash_operations')->where('id', $id)->first();
        if (! $operation) {
            return null;
        }
        if ($operation->actor_id !== $context->actorId || ! hash_equals($operation->fingerprint, $fingerprint)) {
            throw ValidationException::withMessages(['cash' => 'Identificador já utilizado por outra operação. Recarregue a tela.']);
        }

        return $operation->result_id;
    }

    public static function remember(string $id, string $kind, string $fingerprint, int $result, DiningContext $context): void
    {
        DB::table('cash_operations')->insert(['id' => $id, 'kind' => $kind, 'fingerprint' => $fingerprint,
            'actor_id' => $context->actorId, 'result_id' => $result, 'created_at' => now()]);
    }

    /** @param array<string, mixed> $details */
    public static function audit(string $action, int $shift, DiningContext $context, array $details): void
    {
        DB::table('cash_events')->insert(['action' => $action, 'shift_id' => $shift, 'actor_id' => $context->actorId,
            'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
