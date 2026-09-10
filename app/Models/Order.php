<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use CrudTrait, HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['payment_secret'];

    protected function casts(): array
    {
        return ['payment_secret' => 'encrypted', 'subtotal' => 'integer', 'discount_total' => 'integer', 'total' => 'integer', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<TillShift, $this> */
    public function tillShift(): BelongsTo
    {
        return $this->belongsTo(TillShift::class);
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
