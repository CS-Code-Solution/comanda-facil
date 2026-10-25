<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int|null $actor_id
 * @property int|null $auth_version
 * @property string|null $client_hash
 * @property string $phone
 * @property array<string, mixed> $request
 * @property list<array<string, mixed>> $snapshot
 * @property int $total_cents
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $expires_at
 */
class PickupIntent extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $hidden = ['phone', 'client_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'request' => 'array', 'snapshot' => 'array', 'total_cents' => 'integer',
            'created_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
