<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstatusUnidad: string implements HasColor, HasLabel
{
    case Activa = 'activa';
    case FueraOperacion = 'fuera_operacion';
    case TramiteBaja = 'tramite_baja';
    case Baja = 'baja';

    public function getLabel(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::FueraOperacion => 'Fuera de operación',
            self::TramiteBaja => 'Trámite de baja',
            self::Baja => 'Baja',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Activa => 'success',
            self::FueraOperacion => 'warning',
            self::TramiteBaja => 'gray',
            self::Baja => 'danger',
        };
    }
}
