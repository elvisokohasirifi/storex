<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['shop_id', 'user_id', 'action', 'subject_type', 'subject_id', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @param array<string, mixed> $metadata */
    public static function record(?Shop $shop, string $action, ?Model $subject = null, array $metadata = []): void
    {
        static::create([
            'shop_id' => $shop?->id,
            'user_id' => backpack_user()?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
        ]);
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
}
