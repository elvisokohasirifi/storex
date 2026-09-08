<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use CrudTrait, HasFactory, HasUuids;

    protected $fillable = ['name', 'slug', 'description', 'logo', 'banner', 'location', 'contacts', 'email', 'currency'];

    protected $hidden = ['paystack_secret_key', 'paystack_public_key'];

    protected $attributes = ['status' => 'pending', 'currency' => 'GHS'];

    protected function casts(): array
    {
        return ['paystack_secret_key' => 'encrypted', 'paystack_public_key' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::creating(function (Shop $shop) {
            $shop->slug ??= (Str::slug($shop->name) ?: 'shop').'-'.Str::lower(Str::random(8));
            $shop->owner_id ??= backpack_user()?->id;
        });
        static::updating(function (Shop $shop) {
            if ($shop->isDirty(['name', 'description', 'logo', 'banner', 'location', 'contacts', 'email']) && $shop->status !== 'frozen') {
                $shop->status = 'pending';
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<ShopMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ShopMember::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<ProductCategory, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    /** @return HasMany<Brand, $this> */
    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    /** @return HasMany<ShopDiscount, $this> */
    public function discounts(): HasMany
    {
        return $this->hasMany(ShopDiscount::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
