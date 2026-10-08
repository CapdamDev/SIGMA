<?php

namespace App\Filament\Widgets;

use App\Models\Vale;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ConsumoMensual extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Consumo de los últimos 12 meses';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $desde = now()->subMonthsNoOverflow(11)->startOfMonth();

        $filas = Vale::query()->registrados()
            ->join('tipos_combustible', 'tipos_combustible.id', '=', 'vales_combustible.tipo_combustible_id')
            ->where('fecha_carga', '>=', $desde)
            ->groupBy('mes', 'tipos_combustible.clave')
            ->select([
                DB::raw("DATE_FORMAT(fecha_carga, '%Y-%m') AS mes"),
                'tipos_combustible.clave',
                DB::raw('SUM(litros) AS litros'),
            ])
            ->toBase()
            ->get();

        $meses = collect(range(0, 11))->map(fn ($i) => $desde->copy()->addMonthsNoOverflow($i));
        $claves = $filas->pluck('clave')->unique()->values();

        return [
            'datasets' => $claves->map(fn ($clave) => [
                'label' => $clave.' (L)',
                'data' => $meses->map(fn ($mes) => round((float) $filas
                    ->where('clave', $clave)
                    ->firstWhere('mes', $mes->format('Y-m'))?->litros, 2))->all(),
            ])->all(),
            'labels' => $meses->map(fn ($mes) => $mes->translatedFormat('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
