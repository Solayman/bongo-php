<?php

namespace App\Models;

use System\Database\BelongsTo;
use System\Database\Model;

class Product extends Model
{
    protected static $table = 'products';
    protected array $fillable = ['category_id', 'name', 'sku', 'price', 'stock', 'status', 'description'];
    protected array $casts = ['id' => 'int', 'category_id' => 'int', 'price' => 'float', 'stock' => 'int'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
