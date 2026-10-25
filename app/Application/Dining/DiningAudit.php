<?php

namespace App\Application\Dining;

use Illuminate\Support\Facades\DB;

class DiningAudit
{
    /** @param array<string, mixed> $details */
    public static function record(string $action, DiningContext $context, ?int $tableId, array $details = []): void
    {
        DB::table('dining_events')->insert(['table_id' => $tableId, 'actor_id' => $context->actorId,
            'client_hash' => $context->grant?->clientHash(), 'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'occurred_at' => now()]);
    }
}
