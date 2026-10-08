<?php

namespace App\Filament\Widgets;

use App\Models\Vale;
use App\Models\ValeRendimiento;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ResumenMes extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Mes en curso';

    protected function getStats(): array
    {
        $inicio = now()->startOfMonth();
        $actual = $this->totales($inicio, now());
        // Mismo tramo del mes anterior, para comparar parejo.
        $anterior = $this->totales($inicio->copy()->subMonthNoOverflow(), now()->subMonthNoOverflow());

        $revisar = ValeRendimiento::query()
            ->where('lectura_sospechosa', true)
            ->whereIn('id', Vale::query()->registrados()->where('fecha_carga', '>=', $inicio)->select('id'))
            ->count();

        $sinMedidor = Vale::query()->registrados()
            ->where('fecha_carga', '>=', $inicio)
            ->where('medidor_valido', false)
            ->count();

        return [
            $this->comparativo('Gasto', $actual['importe'], $anterior['importe'], fn ($v) => '$'.number_format($v, 2)),
            $this->comparativo('Litros', $actual['litros'], $anterior['litros'], fn ($v) => number_format($v, 2).' L'),
            Stat::make('Vales registrados', number_format($actual['vales']))
                ->description('Precio promedio: $'.number_format($actual['litros'] > 0 ? $actual['importe'] / $actual['litros'] : 0, 2).' por litro'),
            Stat::make('Lecturas por revisar', number_format($revisar))
                ->description($sinMedidor.' cargas sin medidor funcionando')
                ->color($revisar > 0 ? 'warning' : 'success'),
        ];
    }

    private function totales(Carbon $desde, Carbon $hasta): array
    {
        $fila = Vale::query()->registrados()
            ->whereBetween('fecha_carga', [$desde, $hasta])
            ->selectRaw('COUNT(*) AS vales, COALESCE(SUM(litros), 0) AS litros, COALESCE(SUM(importe), 0) AS importe')
            ->toBase()
            ->first();

        return ['vales' => (int) $fila->vales, 'litros' => (float) $fila->litros, 'importe' => (float) $fila->importe];
    }

    private function comparativo(string $titulo, float $actual, float $anterior, callable $formato): Stat
    {
        $stat = Stat::make($titulo, $formato($actual));

        if ($anterior <= 0) {
            return $stat->description('Sin datos del mes anterior');
        }

        $cambio = ($actual - $anterior) / $anterior * 100;

        return $stat
            ->description(sprintf('%+.1f%% vs. mismo periodo del mes anterior', $cambio))
            ->descriptionIcon($cambio >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($cambio > 10 ? 'danger' : ($cambio < -10 ? 'success' : 'gray'));
    }
}
