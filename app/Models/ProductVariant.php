<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use CrudTrait, HasFactory, HasUuids;

    protected $fillable = ['product_id', 'name', 'sku', 'barcode', 'selling_price', 'cost_price', 'quantity', 'reorder_level'];

    protected $attributes = ['reorder_level' => 10];

    protected function casts(): array
    {
        return ['selling_price' => 'decimal:2', 'cost_price' => 'decimal:2', 'quantity' => 'integer', 'reorder_level' => 'integer'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
