<?php

namespace App\Policies;

use App\Models\User;

class OperadorPolicy extends CatalogoPolicy
{
    // Los capturistas pueden dar de alta operadores desde el formulario del vale.
    public function create(User $user): bool
    {
        return $user->puedeCapturar();
    }
}
