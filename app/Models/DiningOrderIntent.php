<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $table_id
 * @property int $cycle
 * @property int|null $actor_id
 * @property string|null $client_hash
 * @property string $source
 * @property array<string, mixed> $request
 * @property list<array<string, mixed>> $snapshot
 * @property int $total_cents
 * @property Carbon $expires_at
 * @property Carbon $created_at
 */
class DiningOrderIntent extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['client_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cycle' => 'integer', 'request' => 'array', 'snapshot' => 'array', 'total_cents' => 'integer', 'expires_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
