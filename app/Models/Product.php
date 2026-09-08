<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $selling_price
 * @property string|null $cost_price
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use CrudTrait, HasFactory, HasUuids;

    protected $fillable = ['shop_id', 'name', 'description', 'image', 'cost_price', 'selling_price', 'quantity', 'barcode', 'sku'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['cost_price' => 'decimal:2', 'selling_price' => 'decimal:2', 'quantity' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (Product $product) {
            if ($product->isDirty(['name', 'description', 'image', 'selling_price', 'barcode', 'sku']) && $product->status !== 'frozen') {
                $product->status = 'pending';
            }
        });
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
