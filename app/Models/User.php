<?php

namespace App\Models;

use System\Database\HasMany;
use System\Database\Model;

class User extends Model
{
    protected static $table = 'users';
    protected array $fillable = ['name', 'email', 'password', 'role', 'phone'];
    protected array $hidden = ['password'];
    protected array $casts = ['id' => 'int'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
