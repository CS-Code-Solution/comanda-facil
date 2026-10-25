<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $intent_id
 * @property int $table_id
 * @property int $session_id
 * @property int|null $cash_shift_id
 * @property int $table_number
 * @property string $modality
 * @property string $source
 * @property int|null $actor_id
 * @property string|null $client_hash
 * @property string $status
 * @property int $version
 * @property int $content_revision
 * @property Carbon|null $status_changed_at
 * @property Carbon|null $started_at
 * @property Carbon|null $ready_at
 * @property Carbon|null $served_at
 * @property Carbon|null $cancelled_at
 * @property string $currency
 * @property int $total_cents
 * @property Carbon $created_at
 * @property-read DiningTable $table
 * @property-read DiningSession $session
 * @property-read Collection<int, DiningOrderItem> $items
 * @property-read User|null $actor
 */
class DiningOrder extends Model
{
    public $timestamps = false;

    protected $hidden = ['client_hash'];

    protected $attributes = ['version' => 1, 'content_revision' => 1];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['total_cents' => 'integer', 'version' => 'integer', 'content_revision' => 'integer', 'created_at' => 'immutable_datetime',
            'status_changed_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'ready_at' => 'immutable_datetime',
            'served_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /** @return BelongsTo<DiningSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(DiningSession::class, 'session_id');
    }

    /** @return HasMany<DiningOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DiningOrderItem::class, 'order_id')->orderBy('position');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
