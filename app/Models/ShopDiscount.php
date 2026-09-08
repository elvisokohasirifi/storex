<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\ShopDiscountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopDiscount extends Model
{
    /** @use HasFactory<ShopDiscountFactory> */
    use CrudTrait, HasFactory, HasUuids;

    protected $fillable = ['shop_id', 'category_id', 'brand_id', 'name', 'scope', 'type', 'value', 'is_active', 'starts_at', 'ends_at'];

    protected $attributes = ['scope' => 'all_products', 'type' => 'percentage', 'is_active' => true];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (ShopDiscount $discount): void {
            if ($discount->scope !== 'category') {
                $discount->category_id = null;
            }
            if ($discount->scope !== 'brand') {
                $discount->brand_id = null;
            }
        });
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
