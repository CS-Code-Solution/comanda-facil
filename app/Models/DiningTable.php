<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $number
 * @property string|null $label
 * @property string $qr_token
 * @property string $qr_hash
 * @property int $qr_version
 * @property int $cycle
 * @property bool $is_active
 * @property bool $is_blocked
 * @property-read Collection<int, DiningSession> $sessions
 * @property-read Collection<int, DiningOrder> $orders
 * @property-read DiningSession|null $currentSession
 */
class DiningTable extends Model
{
    protected $hidden = ['qr_token', 'qr_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['qr_token' => 'encrypted', 'is_active' => 'boolean', 'is_blocked' => 'boolean', 'cycle' => 'integer', 'qr_version' => 'integer'];
    }

    /** @return HasMany<DiningSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(DiningSession::class, 'table_id');
    }

    /** @return HasMany<DiningOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(DiningOrder::class, 'table_id');
    }

    /** @return HasOne<DiningSession, $this> */
    public function currentSession(): HasOne
    {
        return $this->hasOne(DiningSession::class, 'table_id')->whereNull('closed_at');
    }
}
