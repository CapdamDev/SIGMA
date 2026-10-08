<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoMedidor: string implements HasLabel
{
    case Km = 'km';
    case Horas = 'horas';
    case Ninguno = 'ninguno';

    public function getLabel(): string
    {
        return match ($this) {
            self::Km => 'Odómetro (km)',
            self::Horas => 'Horómetro (horas)',
            self::Ninguno => 'Sin medidor',
        };
    }

    public function sufijo(): ?string
    {
        return match ($this) {
            self::Km => 'km',
            self::Horas => 'h',
            self::Ninguno => null,
        };
    }
}
