<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $opened_by_id
 * @property int|null $closed_by_id
 * @property int $opening_cents
 * @property int $version
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property array<string, mixed>|null $closing
 * @property array<string, mixed>|null $report_snapshot
 * @property string|null $closing_reason
 */
class CashShift extends Model
{
    public $timestamps = false;

    protected $attributes = ['version' => 1];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['opening_cents' => 'integer', 'version' => 'integer', 'opened_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'closing' => 'array', 'report_snapshot' => 'array'];
    }
}
