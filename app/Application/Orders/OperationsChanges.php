<?php

namespace App\Application\Orders;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

class OperationsChanges
{
    public static function record(?string $reference = null): void
    {
        DB::table('operations_outbox')->insert(['id' => (string) Str::uuid(), 'order_reference' => $reference,
            'available_at' => now(), 'created_at' => now()]);
    }

    /** One bounded batch, called by the scheduler/CLI outside order transactions. */
    public function publish(int $limit = 50): int
    {
        $count = 0;
        for ($i = 0; $i < $limit; $i++) {
            $event = DB::transaction(function (): ?stdClass {
                $row = DB::table('operations_outbox')->whereNull('delivered_at')->where('available_at', '<=', now())
                    ->where(fn ($q) => $q->whereNull('leased_until')->orWhere('leased_until', '<', now()))
                    ->orderBy('created_at')->lock('FOR UPDATE SKIP LOCKED')->first();
                if ($row) {
                    DB::table('operations_outbox')->where('id', $row->id)->update(['leased_until' => now()->addSeconds(30), 'attempts' => $row->attempts + 1]);
                }

                return $row;
            });
            if (! $event) {
                break;
            }
            try {
                $channels = ['private-operations.kitchen', 'private-operations.dining', 'private-operations.printing', 'private-operations.cashier'];
                if ($event->order_reference) {
                    $channels[] = 'private-dining.order.'.$event->order_reference;
                }
                // Explicit connection prevents an accidentally configured null broadcaster losing events.
                Broadcast::connection('reverb')->broadcast($channels, 'operations.changed', ['id' => $event->id]);
                DB::table('operations_outbox')->where('id', $event->id)->update(['delivered_at' => now(), 'leased_until' => null, 'last_error' => null]);
                $count++;
            } catch (Throwable) {
                // Never retain exception messages containing signed URLs, payloads or secrets.
                DB::table('operations_outbox')->where('id', $event->id)->update(['leased_until' => null,
                    'available_at' => now()->addSeconds(min(300, 2 ** min(8, $event->attempts + 1))), 'last_error' => 'broadcast_unavailable']);
                break;
            }
        }

        return $count;
    }
}
