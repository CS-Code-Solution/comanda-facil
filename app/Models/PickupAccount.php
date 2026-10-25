<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $phone
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $closed_at
 * @property int|null $closed_by_id
 */
class PickupAccount extends Model
{
    public $timestamps = false;

    protected $hidden = ['phone'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'opened_at' => 'immutable_datetime', 'settled_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    /** @return HasMany<DiningOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(DiningOrder::class, 'pickup_account_id');
    }
}
