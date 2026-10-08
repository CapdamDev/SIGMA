<?php

namespace Tests\Feature;

use App\Models\Direccion;
use App\Models\Responsable;
use App\Models\TipoCombustible;
use App\Models\Unidad;
use App\Models\Vale;
use App\Services\ImportSnapshotService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase solo limpia tablas, no el disco: cada prueba empieza
        // sin respaldos previos de otras pruebas de esta clase.
        Storage::disk('local')->deleteDirectory('importacion/respaldos');
    }

    public function test_crear_y_restaurar_devuelve_los_datos_exactos(): void
    {
        $this->seed(DatabaseSeeder::class);

        $direccion = Direccion::create(['nombre' => 'Original']);
        $responsable = Responsable::create(['nombre' => 'Responsable Original', 'direccion_id' => $direccion->id]);
        $unidad = Unidad::create([
            'numero_economico' => 'ECO 001',
            'tipo_combustible_id' => TipoCombustible::where('clave', 'MAGNA')->value('id'),
            'direccion_id' => $direccion->id,
            'responsable_id' => $responsable->id,
        ]);
        Vale::create([
            'unidad_id' => $unidad->id,
            'fecha_carga' => now()->subDay(),
            'litros' => 30,
            'importe' => 700,
        ]);

        $servicio = app(ImportSnapshotService::class);
        $archivo = $servicio->crear('prueba');

        // Simula que la importación salió mal: se borra y modifica todo.
        Vale::query()->delete();
        $unidad->delete();
        $responsable->update(['nombre' => 'Modificado']);
        $direccion->update(['nombre' => 'Modificado']);
        Direccion::create(['nombre' => 'Intrusa']);

        $servicio->restaurar($archivo);

        $this->assertSame('Original', $direccion->fresh()->nombre);
        $this->assertSame('Responsable Original', $responsable->fresh()->nombre);
        $this->assertNotNull(Unidad::where('numero_economico', 'ECO 001')->first());
        $this->assertSame(1, Vale::query()->count());
        $this->assertDatabaseMissing('direcciones', ['nombre' => 'Intrusa']);

        // La columna generada (precio_litro) debe recalcularse sola, no viajar en el respaldo.
        $valeRestaurado = Vale::first();
        $this->assertEquals(round(700 / 30, 3), (float) $valeRestaurado->precio_litro);
    }

    public function test_listar_ordena_del_mas_reciente_al_mas_antiguo(): void
    {
        $servicio = app(ImportSnapshotService::class);

        $primero = $servicio->crear('primero');
        usleep(1000);
        $segundo = $servicio->crear('segundo');

        $listado = $servicio->listar();

        $this->assertSame($segundo, $listado[0]['archivo']);
        $this->assertSame($primero, $listado[1]['archivo']);
    }

    public function test_eliminar_borra_el_respaldo(): void
    {
        $servicio = app(ImportSnapshotService::class);
        $archivo = $servicio->crear('temporal');

        $servicio->eliminar($archivo);

        $this->assertEmpty($servicio->listar());
    }

    public function test_restaurar_con_archivo_inexistente_lanza_excepcion(): void
    {
        $servicio = app(ImportSnapshotService::class);

        $this->expectException(\RuntimeException::class);

        $servicio->restaurar('no-existe.json');
    }
}
