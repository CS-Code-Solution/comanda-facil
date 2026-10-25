<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $entity
 * @property int $entity_id
 * @property string $action
 * @property string $source
 * @property array<string, mixed>|null $before
 * @property array<string, mixed> $after
 * @property Carbon $occurred_at
 * @property-read User|null $actor
 */
class CatalogEvent extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'occurred_at' => 'datetime'];
    }

    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
