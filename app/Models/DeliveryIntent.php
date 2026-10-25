<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $phone
 * @property array<string, string> $address
 * @property array<string, mixed> $coverage
 * @property array<string, mixed> $request
 * @property list<array<string, mixed>> $snapshot
 * @property int $total_cents
 * @property CarbonImmutable $expires_at
 */
class DeliveryIntent extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['phone', 'address', 'client_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'address' => 'encrypted:array', 'coverage' => 'array', 'request' => 'array', 'snapshot' => 'array',
            'total_cents' => 'integer', 'created_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
