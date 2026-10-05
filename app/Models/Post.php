<?php

declare(strict_types=1);

namespace App\Models;

use System\Database\BelongsTo;
use System\Database\Model;
use System\Storage\Storage;

class Post extends Model
{
    protected static $table = 'posts';
    protected array $fillable = ['user_id', 'title', 'slug', 'featured_image', 'content', 'status'];
    protected array $casts = ['id' => 'int', 'user_id' => 'int'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * "Hello World" -> "hello-world", or "hello-world-2" when that slug is already taken
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-') ?: 'post';
        $slug = $base;
        for ($i = 2;; $i++) {
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

    /**
     * Public URL of the featured image, or null
     */
    public function featuredImageUrl(): ?string
    {
        if (empty($this->featured_image)) {
            return null;
        }

        if (str_starts_with($this->featured_image, 'http://') || str_starts_with($this->featured_image, 'https://') || str_starts_with($this->featured_image, '/')) {
            return $this->featured_image;
        }

        return Storage::url($this->featured_image);
    }
}
