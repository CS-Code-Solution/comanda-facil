<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $phone
 * @property array<string, string> $address
 * @property array<string, mixed> $coverage
 * @property int $fee_cents
 * @property int $minimum_cents
 * @property int|null $courier_id
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $closed_at
 * @property int|null $closed_by_id
 */
class DeliveryAccount extends Model
{
    public $timestamps = false;

    protected $hidden = ['phone', 'address'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'address' => 'encrypted:array', 'coverage' => 'array', 'fee_cents' => 'integer', 'minimum_cents' => 'integer',
            'opened_at' => 'immutable_datetime', 'settled_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    /** @return HasMany<DiningOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(DiningOrder::class, 'delivery_account_id');
    }
}
