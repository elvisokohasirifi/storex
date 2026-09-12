<?php

namespace App\Models;

use App\Models\Traits\LogsSafeActivity;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $selling_price
 * @property string|null $sale_price
 * @property string|null $cost_price
 * @property int|null $quantity
 * @property int $reorder_level
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use CrudTrait, HasFactory, HasUuids, LogsSafeActivity;

    protected $fillable = ['shop_id', 'category_id', 'brand_id', 'name', 'description', 'image', 'cost_price', 'selling_price', 'sale_price', 'quantity', 'reorder_level', 'barcode', 'sku', 'visibility'];

    protected $attributes = ['status' => 'approved', 'visibility' => 'published', 'reorder_level' => 10];

    protected function casts(): array
    {
        return ['cost_price' => 'decimal:2', 'selling_price' => 'decimal:2', 'sale_price' => 'decimal:2', 'quantity' => 'integer', 'reorder_level' => 'integer'];
    }

    protected static function booted(): void
    {
        static::created(function (Product $product): void {
            if ($product->quantity === null || $product->quantity <= 0) {
                return;
            }

            $shop = $product->shop()->first();
            if (! $shop?->enable_inventory_management) {
                return;
            }

            $product->movements()->create([
                'shop_id' => $product->shop_id,
                'type' => 'opening_stock',
                'quantity' => $product->quantity,
                'reference_type' => self::class,
                'reference_id' => $product->id,
                'reason' => 'Opening stock from product creation',
                'user_id' => backpack_user()?->id,
            ]);
        });
    }

    public function baseSaleAmount(): string
    {
        return $this->sale_price ?? $this->selling_price;
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<StockBatch, $this> */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
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
