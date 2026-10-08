<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->esAdmin();
    }

    public function create(User $user): bool
    {
        return $user->esAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->esAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return false; // se desactivan
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
