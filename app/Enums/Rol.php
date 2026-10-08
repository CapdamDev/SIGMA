<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Rol: string implements HasLabel
{
    case Admin = 'admin';
    case Capturista = 'capturista';
    case Consulta = 'consulta';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Capturista => 'Capturista',
            self::Consulta => 'Consulta',
        };
    }
}
