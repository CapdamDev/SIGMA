<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogos: todos consultan, solo administradores editan.
 * No se borra nada que pueda tener vales ligados: se desactiva.
 */
abstract class CatalogoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->esAdmin();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->esAdmin();
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
