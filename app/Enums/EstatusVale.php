<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstatusVale: string implements HasColor, HasLabel
{
    case Registrado = 'registrado';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Registrado => 'Registrado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Registrado => 'success',
            self::Cancelado => 'danger',
        };
    }
}
