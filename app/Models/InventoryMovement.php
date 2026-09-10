<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use CrudTrait, HasFactory, HasUuids;

    public const UPDATED_AT = null;

    public const TYPES = [
        'purchase',
        'sale',
        'return',
        'adjustment',
        'damage',
        'expired',
        'transfer_in',
        'transfer_out',
        'opening_stock',
    ];

    protected $fillable = ['shop_id', 'product_id', 'type', 'quantity', 'reference_type', 'reference_id', 'reason', 'user_id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
