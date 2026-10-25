<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $reference
 * @property string $intent_id
 * @property int $session_id
 * @property int $shift_id
 * @property int $actor_id
 * @property string $method
 * @property int $amount_cents
 * @property int|null $received_cents
 * @property int $change_cents
 * @property string|null $external_reference
 * @property array<string, mixed> $snapshot
 * @property CarbonImmutable $created_at
 * @property bool $has_pending_refund
 */
class CashPayment extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'amount_cents' => 'integer', 'received_cents' => 'integer', 'change_cents' => 'integer', 'created_at' => 'immutable_datetime', 'has_pending_refund' => 'boolean'];
    }
}
