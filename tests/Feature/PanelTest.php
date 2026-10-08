<?php

namespace Tests\Feature;

use App\Enums\EstatusVale;
use App\Enums\Rol;
use App\Filament\Resources\Direcciones\Pages\ManageDirecciones;
use App\Filament\Resources\Operadores\Pages\ManageOperadores;
use App\Filament\Resources\Responsables\Pages\ManageResponsables;
use App\Filament\Resources\TiposCombustible\Pages\ManageTiposCombustible;
use App\Filament\Resources\Unidades\Pages\CreateUnidad;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Filament\Resources\Unidades\Pages\ListUnidades;
use App\Filament\Resources\Unidades\RelationManagers\ValesRelationManager;
use App\Filament\Resources\Usuarios\Pages\ManageUsuarios;
use App\Filament\Resources\Vales\Pages\CreateVale;
use App\Filament\Resources\Vales\Pages\EditVale;
use App\Filament\Resources\Vales\Pages\ListVales;
use App\Filament\Widgets\ConsumoMensual;
use App\Filament\Widgets\GastoPorDireccion;
use App\Filament\Widgets\ResumenMes;
use App\Models\Direccion;
use App\Models\Responsable;
use App\Models\TipoCombustible;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Vale;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Unidad $unidad;

    private Direccion $direccion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::factory()->create(); // el primero queda como admin
        $this->direccion = Direccion::create(['nombre' => 'Operaciones']);
        $responsable = Responsable::create(['titulo' => 'ING.', 'nombre' => 'Prueba Responsable', 'direccion_id' => $this->direccion->id]);

        $this->unidad = Unidad::create([
            'numero_economico' => 'ECO 001',
            'descripcion' => 'CAMIONETA ESTACAS',
            'tipo_combustible_id' => TipoCombustible::where('clave', 'MAGNA')->value('id'),
            'capacidad_tanque_l' => 75,
            'rendimiento_esperado' => 10,
            'direccion_id' => $this->direccion->id,
            'responsable_id' => $responsable->id,
        ]);

        $this->actingAs($this->admin);
    }

    private function vale(array $datos = []): Vale
    {
        return Vale::create([
            'unidad_id' => $this->unidad->id,
            'fecha_carga' => now()->subDays(2),
            'lectura_medidor' => 1000,
            'litros' => 30,
            'importe' => 718.50,
            ...$datos,
        ]);
    }

    public function test_primer_usuario_es_admin(): void
    {
        $this->assertSame(Rol::Admin, $this->admin->fresh()->rol);
        $this->assertSame(Rol::Consulta, User::factory()->create()->fresh()->rol);
    }

    public function test_todas_las_pantallas_cargan(): void
    {
        $vale = $this->vale();

        $this->get('/')->assertOk();

        foreach ([
            ListVales::class, CreateVale::class, ListUnidades::class, CreateUnidad::class,
            ManageDirecciones::class, ManageResponsables::class, ManageOperadores::class,
            ManageTiposCombustible::class, ManageUsuarios::class,
            ResumenMes::class, GastoPorDireccion::class, ConsumoMensual::class,
        ] as $componente) {
            Livewire::test($componente)->assertOk();
        }

        Livewire::test(EditVale::class, ['record' => $vale->getRouteKey()])->assertOk();
        Livewire::test(EditUnidad::class, ['record' => $this->unidad->getRouteKey()])->assertOk();
        Livewire::test(ValesRelationManager::class, ['ownerRecord' => $this->unidad, 'pageClass' => EditUnidad::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$vale]);
    }

    public function test_crear_vale_toma_la_asignacion_de_la_unidad_y_calcula_rendimiento(): void
    {
        $this->vale();

        Livewire::test(CreateVale::class)
            ->fillForm([
                'unidad_id' => $this->unidad->id,
                'fecha_carga' => now()->subHour()->format('Y-m-d H:i'),
                'lectura_medidor' => 1300,
                'litros' => 30,
                'importe' => 718.50,
            ])
            ->assertSchemaStateSet(['direccion_id' => $this->direccion->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = Vale::latest('id')->first();
        $this->assertSame($this->direccion->id, $nuevo->direccion_id);
        $this->assertSame($this->admin->id, $nuevo->capturado_por);
        $this->assertEquals(23.95, (float) $nuevo->fresh()->precio_litro);
        $this->assertEquals(10.0, (float) $nuevo->rendimiento->rendimiento);
    }

    public function test_lectura_que_retrocede_se_rechaza_salvo_confirmacion(): void
    {
        $this->vale();

        $datos = [
            'unidad_id' => $this->unidad->id,
            'fecha_carga' => now()->subHour()->format('Y-m-d H:i'),
            'lectura_medidor' => 900,
            'litros' => 30,
            'importe' => 718.50,
        ];

        Livewire::test(CreateVale::class)
            ->fillForm($datos)
            ->call('create')
            ->assertHasFormErrors(['lectura_medidor']);

        Livewire::test(CreateVale::class)
            ->fillForm([...$datos, 'confirmar_lectura' => true])
            ->call('create')
            ->assertHasFormErrors(['observaciones' => 'required']);

        Livewire::test(CreateVale::class)
            ->fillForm([...$datos, 'confirmar_lectura' => true, 'observaciones' => 'Se cambió el tablero'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Vale::latest('id')->first()->rendimiento->lectura_sospechosa);
    }

    public function test_recorrido_exagerado_se_rechaza(): void
    {
        $this->vale();

        Livewire::test(CreateVale::class)
            ->fillForm([
                'unidad_id' => $this->unidad->id,
                'fecha_carga' => now()->subHour()->format('Y-m-d H:i'),
                'lectura_medidor' => 13000, // un cero de más
                'litros' => 30,
                'importe' => 718.50,
            ])
            ->call('create')
            ->assertHasFormErrors(['lectura_medidor']);
    }

    public function test_medidor_descompuesto_no_exige_lectura(): void
    {
        Livewire::test(CreateVale::class)
            ->fillForm([
                'unidad_id' => $this->unidad->id,
                'fecha_carga' => now()->subHour()->format('Y-m-d H:i'),
                'medidor_valido' => false,
                'litros' => 30,
                'importe' => 718.50,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $vale = Vale::latest('id')->first();
        $this->assertFalse($vale->medidor_valido);
        $this->assertNull($vale->lectura_medidor);
    }

    public function test_cancelar_vale_solo_admin(): void
    {
        $vale = $this->vale();

        Livewire::test(ListVales::class)
            ->callAction(TestAction::make('cancelar')->table($vale), ['motivo_cancelacion' => 'Duplicado'])
            ->assertHasNoFormErrors();

        $this->assertSame(EstatusVale::Cancelado, $vale->fresh()->estatus);

        $capturista = User::factory()->create(['rol' => Rol::Capturista]);
        $otro = $this->vale(['fecha_carga' => now()->subDay(), 'lectura_medidor' => 1200]);

        $this->actingAs($capturista);
        Livewire::test(ListVales::class)
            ->assertActionHidden(TestAction::make('cancelar')->table($otro));
    }

    public function test_consulta_no_puede_capturar_ni_editar_catalogos(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::Consulta]));

        $this->get('/vales/create')->assertForbidden();
        $this->get('/unidades/create')->assertForbidden();
        $this->get('/usuarios')->assertForbidden();
        $this->get('/vales')->assertOk();
    }

    public function test_exportar_a_excel(): void
    {
        $this->vale();

        Livewire::test(ListVales::class)
            ->callAction(TestAction::make('exportar')->table())
            ->assertFileDownloaded();
    }
}
