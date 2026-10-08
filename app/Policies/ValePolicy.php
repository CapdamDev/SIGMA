<?php

namespace App\Policies;

use App\Enums\EstatusVale;
use App\Models\User;
use App\Models\Vale;

class ValePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vale $vale): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->puedeCapturar();
    }

    public function update(User $user, Vale $vale): bool
    {
        return $user->puedeCapturar() && $vale->estatus === EstatusVale::Registrado;
    }

    /** Los vales nunca se borran: se cancelan con motivo. */
    public function delete(User $user, Vale $vale): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function cancelar(User $user, Vale $vale): bool
    {
        return $user->esAdmin() && $vale->estatus === EstatusVale::Registrado;
    }
}
