<?php

namespace App\Models;

use App\Models\Traits\LogsSafeActivity;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $shop_id
 * @property string $name
 * @property string|null $description
 */
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use CrudTrait, HasFactory, HasUuids, LogsSafeActivity;

    protected $fillable = ['shop_id', 'name', 'description'];

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
