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
 * @property string|null $sale_price
 * @property string|null $cost_price
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use CrudTrait, HasFactory, HasUuids;

    protected $fillable = ['shop_id', 'category_id', 'brand_id', 'name', 'description', 'image', 'cost_price', 'selling_price', 'sale_price', 'quantity', 'barcode', 'sku', 'visibility'];

    protected $attributes = ['status' => 'approved', 'visibility' => 'published'];

    protected function casts(): array
    {
        return ['cost_price' => 'decimal:2', 'selling_price' => 'decimal:2', 'sale_price' => 'decimal:2', 'quantity' => 'integer'];
    }

    public function baseSaleAmount(): string
    {
        return $this->sale_price ?? $this->selling_price;
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
