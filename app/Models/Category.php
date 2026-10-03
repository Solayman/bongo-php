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

    /** "Home & Kitchen" → "home-kitchen", or "home-kitchen-2" when that is taken (by another category than $ignoreId) */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'category';
        $slug = $base;
        for ($i = 2; ; $i++) {
            $query = static::where('slug', $slug);
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
            if ($query->count() === 0) {
                return $slug;
            }
            $slug = "$base-$i";
        }
    }
}
