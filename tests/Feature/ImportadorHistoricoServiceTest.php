<?php

namespace Tests\Feature;

use App\Models\Direccion;
use App\Models\Responsable;
use App\Models\Unidad;
use App\Models\Vale;
use App\Services\ImportadorHistoricoService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Tests\TestCase;

class ImportadorHistoricoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function crearArchivoDePrueba(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'import_test_').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($ruta);

        $writer->getCurrentSheet()->setName('direcciones');
        $writer->addRow(Row::fromValues(['id_direccion', 'nombre_direccion']));
        $writer->addRow(Row::fromValues([1, 'Operaciones']));

        $writer->addNewSheetAndMakeItCurrent()->setName('usuarios_responsables');
        $writer->addRow(Row::fromValues(['id_usuario', 'nombre_completo']));
        $writer->addRow(Row::fromValues([1, 'Juan Perez']));

        $writer->addNewSheetAndMakeItCurrent()->setName('unidades');
        $writer->addRow(Row::fromValues([
            'eco_nombre', 'descripcion', 'capacidad_tanque', 'rendimiento_km_litro',
            'modelo', 'id_direccion', 'id_usuario_responsable', 'marca', 'placa',
            'tipo_combustible', 'Columna 1',
        ]));
        $writer->addRow(Row::fromValues([
            'ECO 001', 'CAMIONETA ESTACAS', 75, 10, 2020, 1, 1, 'FORD', 'ABC123', 'MAGNA', 101,
        ]));

        $writer->addNewSheetAndMakeItCurrent()->setName('vales_gasolina');
        $writer->addRow(Row::fromValues([
            'id_unidad', 'fecha_carga', 'litros', 'total_dinero', 'folio_vale',
            'observaciones', 'km_carga', 'tipo_combustible', 'departamento', 'Columna 1',
        ]));
        $writer->addRow(Row::fromValues([
            101, '2026-01-01 10:00:00', 30, 700, 5, '', 1000, 'MAGNA', 'Operaciones', '2026-01-01 10:00:00',
        ]));

        $writer->close();

        return $ruta;
    }

    public function test_dry_run_no_guarda_nada(): void
    {
        $this->seed(DatabaseSeeder::class);
        $archivo = $this->crearArchivoDePrueba();

        $resultado = app(ImportadorHistoricoService::class)->ejecutar($archivo, dryRun: true);

        $this->assertSame(1, $resultado['direcciones']);
        $this->assertSame(1, $resultado['responsables']);
        $this->assertSame(1, $resultado['unidades']);
        $this->assertSame(1, $resultado['importados']);
        $this->assertSame(0, $resultado['omitidos']);
        $this->assertSame(0, $resultado['avisos']);

        $this->assertSame(0, Direccion::query()->count());
        $this->assertSame(0, Unidad::query()->count());
        $this->assertSame(0, Vale::query()->count());

        unlink($archivo);
    }

    public function test_importacion_real_guarda_los_datos_y_calcula_precio_litro(): void
    {
        $this->seed(DatabaseSeeder::class);
        $archivo = $this->crearArchivoDePrueba();

        $procesos = [];
        $resultado = app(ImportadorHistoricoService::class)->ejecutar(
            $archivo,
            dryRun: false,
            onProgress: function (int $procesado, int $total) use (&$procesos) {
                $procesos[] = [$procesado, $total];
            },
        );

        $this->assertSame(1, $resultado['importados']);
        $this->assertSame([[1, 1]], $procesos);

        $this->assertSame(1, Direccion::query()->count());
        $this->assertSame('Operaciones', Direccion::first()->nombre);
        $this->assertSame('Juan Perez', Responsable::first()->nombre);
        $this->assertSame('ECO 001', Unidad::first()->numero_economico);

        $vale = Vale::first();
        $this->assertEquals(round(700 / 30, 3), (float) $vale->precio_litro);
        $this->assertSame($vale->unidad_id, Unidad::first()->id);
        $this->assertSame($vale->direccion_id, Direccion::first()->id);

        unlink($archivo);
    }

    public function test_segunda_importacion_sin_fresh_se_rechaza(): void
    {
        $this->seed(DatabaseSeeder::class);
        $archivo = $this->crearArchivoDePrueba();
        $servicio = app(ImportadorHistoricoService::class);

        $servicio->ejecutar($archivo, dryRun: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ya hay vales en la base de datos');

        $servicio->ejecutar($archivo, dryRun: false);

        unlink($archivo);
    }

    // Nota: no se prueba aquí el camino --fresh llamando a ejecutar() dos veces
    // en una misma prueba. vaciarTablas() usa TRUNCATE, que en MySQL hace commit
    // implícito y rompe la transacción con la que RefreshDatabase envuelve cada
    // prueba (error "SAVEPOINT ... does not exist"). Es una limitación del
    // entorno de pruebas, no del código: vaciarTablas() es el mismo TRUNCATE que
    // ya usaba el comando original, que siempre corre en su propio proceso sin
    // una transacción externa.
}
