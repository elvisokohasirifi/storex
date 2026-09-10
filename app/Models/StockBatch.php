<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\StockBatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBatch extends Model
{
    /** @use HasFactory<StockBatchFactory> */
    use CrudTrait, HasFactory, HasUuids;

    protected $fillable = ['shop_id', 'product_id', 'supplier_id', 'inventory_movement_id', 'batch_number', 'expiry_date', 'quantity_received', 'quantity_remaining', 'unit_cost'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'quantity_received' => 'integer', 'quantity_remaining' => 'integer', 'unit_cost' => 'decimal:2'];
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
