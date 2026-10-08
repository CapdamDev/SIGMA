<?php

namespace App\Console\Commands;

use App\Services\ImportadorHistoricoService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 *   php artisan combustible:importar storage/app/Control_Flotillas.xlsx --dry-run
 *   php artisan combustible:importar storage/app/Control_Flotillas.xlsx --fresh
 *
 * La lógica de importación vive en App\Services\ImportadorHistoricoService
 * (también la usa el panel de administración).
 */
class ImportarHistorico extends Command
{
    protected $signature = 'combustible:importar
        {archivo : Ruta al XLSX exportado de Google Sheets}
        {--dry-run : Procesa y reporta, pero no guarda nada}
        {--fresh : Borra unidades, catálogos y vales antes de importar (solo antes de salir a producción)}';

    protected $description = 'Importa y limpia el histórico de vales desde el XLSX de Google Sheets';

    public function handle(ImportadorHistoricoService $servicio): int
    {
        $archivo = $this->argument('archivo');

        $this->info('Leyendo '.basename($archivo).'…');

        $barra = null;

        try {
            $resultado = $servicio->ejecutar(
                $archivo,
                dryRun: (bool) $this->option('dry-run'),
                fresh: (bool) $this->option('fresh'),
                onProgress: function (int $procesado, int $total) use (&$barra) {
                    if (! $barra) {
                        $barra = $this->output->createProgressBar($total);
                        $barra->start();
                    } else {
                        $barra->advance();
                    }
                },
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $barra?->finish();
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('Modo --dry-run: no se guardó nada.');
        }

        $this->table(['Concepto', 'Cantidad'], [
            ['Direcciones', $resultado['direcciones']],
            ['Responsables (personas)', $resultado['responsables']],
            ['Unidades', $resultado['unidades']],
            ['Operadores detectados en folio', $resultado['operadores']],
            ['Vales importados', $resultado['importados']],
            ['Vales omitidos', $resultado['omitidos']],
            ['Avisos en el reporte', $resultado['avisos']],
        ]);

        if ($resultado['reporte_csv']) {
            $this->warn("Revisa el reporte: {$resultado['reporte_csv']}");
        }

        return self::SUCCESS;
    }
}
