<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $table_id
 * @property int|null $session_id
 * @property int $cycle
 * @property string $client_hash
 * @property string $status
 * @property int|null $claimed_by_id
 * @property int|null $completed_by_id
 * @property Carbon $created_at
 * @property Carbon|null $claimed_at
 * @property Carbon|null $completed_at
 * @property-read DiningTable $table
 * @property-read User|null $claimant
 */
class DiningCall extends Model
{
    public $timestamps = false;

    protected $hidden = ['client_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cycle' => 'integer', 'created_at' => 'immutable_datetime', 'claimed_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /** @return BelongsTo<User, $this> */
    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_id');
    }
}
