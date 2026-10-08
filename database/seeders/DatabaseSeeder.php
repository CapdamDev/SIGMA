<?php

namespace Database\Seeders;

use App\Models\TipoCombustible;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'MAGNA' => 'Gasolina Magna',
            'PREMIUM' => 'Gasolina Premium',
            'DIESEL' => 'Diésel',
        ] as $clave => $nombre) {
            TipoCombustible::firstOrCreate(['clave' => $clave], ['nombre' => $nombre]);
        }
    }
}
