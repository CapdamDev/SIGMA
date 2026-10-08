<?php

namespace App\Filament\Widgets;

use App\Models\Vale;
use Filament\Widgets\ChartWidget;

class GastoPorDireccion extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Gasto por dirección';

    protected ?string $maxHeight = '320px';

    public ?string $filter = 'mes';

    protected function getFilters(): ?array
    {
        return [
            'mes' => 'Este mes',
            'anterior' => 'Mes anterior',
            'anio' => 'Este año',
        ];
    }

    protected function getData(): array
    {
        [$desde, $hasta] = match ($this->filter) {
            'anterior' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'anio' => [now()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()],
        };

        $filas = Vale::query()->registrados()
            ->leftJoin('direcciones', 'direcciones.id', '=', 'vales_combustible.direccion_id')
            ->whereBetween('fecha_carga', [$desde, $hasta])
            ->groupBy('direcciones.nombre')
            ->selectRaw("COALESCE(direcciones.nombre, 'Sin dirección') AS nombre, SUM(importe) AS total")
            ->orderByDesc('total')
            ->toBase()
            ->get();

        return [
            'datasets' => [[
                'label' => 'Importe ($)',
                'data' => $filas->pluck('total')->map(fn ($v) => round((float) $v, 2))->all(),
            ]],
            'labels' => $filas->pluck('nombre')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
