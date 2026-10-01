<?php

namespace App\Models;

use System\Database\HasMany;
use System\Database\Model;

class Category extends Model
{
    protected static $table = 'categories';
    protected array $fillable = ['name', 'slug'];
    protected array $casts = ['id' => 'int'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
