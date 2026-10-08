<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClaseUnidad: string implements HasLabel
{
    case Vehiculo = 'vehiculo';
    case Motocicleta = 'motocicleta';
    case Maquinaria = 'maquinaria';
    case Pipa = 'pipa';
    case Bidon = 'bidon';
    case Generador = 'generador';
    case Concepto = 'concepto'; // Siniestros, Sindicato, apoyos externos

    public function getLabel(): string
    {
        return match ($this) {
            self::Vehiculo => 'Vehículo',
            self::Motocicleta => 'Motocicleta',
            self::Maquinaria => 'Maquinaria',
            self::Pipa => 'Pipa / camión',
            self::Bidon => 'Bidón',
            self::Generador => 'Generador',
            self::Concepto => 'Concepto / externo',
        };
    }

    public function medidorPorDefecto(): TipoMedidor
    {
        return match ($this) {
            self::Maquinaria => TipoMedidor::Horas,
            self::Bidon, self::Generador, self::Concepto => TipoMedidor::Ninguno,
            default => TipoMedidor::Km,
        };
    }
}
