<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $table_id
 * @property int $cycle
 * @property Carbon $opened_at
 * @property Carbon|null $settled_at
 * @property Carbon|null $closed_at
 * @property int|null $opened_by_id
 * @property int|null $closed_by_id
 * @property-read DiningTable $table
 * @property-read Collection<int, DiningOrder> $orders
 */
class DiningSession extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cycle' => 'integer', 'opened_at' => 'immutable_datetime', 'settled_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /** @return HasMany<DiningOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(DiningOrder::class, 'session_id');
    }
}
