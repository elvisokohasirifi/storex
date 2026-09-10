<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use CrudTrait, HasFactory, HasUuids;

    protected $fillable = ['shop_id', 'supplier_id', 'reference', 'status', 'total_cost', 'notes', 'received_at', 'created_by'];

    protected $attributes = ['status' => 'draft', 'total_cost' => 0];

    protected function casts(): array
    {
        return ['total_cost' => 'integer', 'received_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $purchaseOrder): void {
            $purchaseOrder->reference ??= 'PO-'.Str::upper(Str::random(8));
            $purchaseOrder->created_by ??= backpack_user()?->id;
        });
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
