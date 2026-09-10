<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\TillShiftFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TillShift extends Model
{
    /** @use HasFactory<TillShiftFactory> */
    use CrudTrait, HasFactory, HasUuids;

    protected $fillable = ['shop_id', 'user_id', 'status', 'opening_cash', 'expected_cash', 'actual_cash', 'notes', 'opened_at', 'closed_at'];

    protected $attributes = ['status' => 'open', 'opening_cash' => 0, 'expected_cash' => 0];

    protected function casts(): array
    {
        return ['opening_cash' => 'integer', 'expected_cash' => 'integer', 'actual_cash' => 'integer', 'opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
