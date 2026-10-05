<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(object $user, User $model): bool
    {
        return ($user->id ?? null) === $model->id || ($user->role ?? null) === 'admin';
    }

    public function delete(object $user, User $model): bool
    {
        return ($user->role ?? null) === 'admin';
    }
}
