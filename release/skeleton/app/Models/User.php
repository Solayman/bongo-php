<?php

namespace App\Models;

use System\Database\Model;

class User extends Model
{
    protected static $table = 'users';
    protected array $fillable = ['name', 'email', 'password', 'role', 'phone'];
    protected array $hidden = ['password', 'remember_token'];
    protected array $casts = ['id' => 'int'];
}
