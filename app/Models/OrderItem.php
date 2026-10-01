<?php

namespace App\Models;

use System\Database\BelongsTo;
use System\Database\Model;

class OrderItem extends Model
{
    protected static $table = 'order_items';
    protected array $fillable = ['order_id', 'product_id', 'product_name', 'quantity', 'unit_price'];
    protected array $casts = ['quantity' => 'int', 'unit_price' => 'float'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
