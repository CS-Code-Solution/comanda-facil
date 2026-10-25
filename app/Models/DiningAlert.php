<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $table_id
 * @property int $session_id
 * @property string $type
 * @property int|null $acknowledged_by_id
 * @property Carbon|null $acknowledged_at
 * @property Carbon $created_at
 * @property-read DiningTable $table
 */
class DiningAlert extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }
}
