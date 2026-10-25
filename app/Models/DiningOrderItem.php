<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $variant_id
 * @property int $quantity
 * @property int $position
 * @property string|null $note
 * @property array<string, mixed> $snapshot
 * @property int $total_cents
 */
class DiningOrderItem extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'total_cents' => 'integer', 'quantity' => 'integer'];
    }
}
