<?php

namespace App\Models;

use App\Models\Traits\LogsSafeActivity;
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
    use CrudTrait, HasFactory, HasUuids, LogsSafeActivity;

    protected $fillable = ['name', 'slug', 'description', 'logo', 'banner', 'location', 'contacts', 'email', 'currency', 'enable_inventory_management', 'momo_number', 'momo_account_name'];

    protected $hidden = ['paystack_secret_key', 'paystack_public_key'];

    protected $attributes = ['status' => 'pending', 'currency' => 'GHS'];

    protected function casts(): array
    {
        return ['paystack_secret_key' => 'encrypted', 'paystack_public_key' => 'encrypted', 'enable_inventory_management' => 'boolean'];
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

    /** @return HasMany<InventoryMovement, $this> */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** @return HasMany<ShopDiscount, $this> */
    public function discounts(): HasMany
    {
        return $this->hasMany(ShopDiscount::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<Supplier, $this> */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @return HasMany<TillShift, $this> */
    public function tillShifts(): HasMany
    {
        return $this->hasMany(TillShift::class);
    }

    /** @return HasMany<StockBatch, $this> */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
