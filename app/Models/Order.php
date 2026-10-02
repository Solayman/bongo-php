<?php

namespace App\Models;

use System\Database\BelongsTo;
use System\Database\HasMany;
use System\Database\Model;

class Order extends Model
{
    protected static $table = 'orders';
    protected array $fillable = ['user_id', 'order_number', 'status', 'total', 'shipping_address'];
    protected array $casts = ['total' => 'float'];

    public const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    /** Bootstrap badge colour for a status */
    public static function badge(string $status): string
    {
        return match ($status) {
            'pending'    => 'text-bg-warning',
            'processing' => 'text-bg-info',
            'shipped'    => 'text-bg-primary',
            'delivered'  => 'text-bg-success',
            'cancelled'  => 'text-bg-secondary',
            default      => 'text-bg-light',
        };
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
