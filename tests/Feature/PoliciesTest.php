<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Direccion;
use App\Models\Operador;
use App\Models\Responsable;
use App\Models\TipoCombustible;
use App\Models\Unidad;
use App\Models\User;
use App\Policies\DireccionPolicy;
use App\Policies\OperadorPolicy;
use App\Policies\ResponsablePolicy;
use App\Policies\TipoCombustiblePolicy;
use App\Policies\UnidadPolicy;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Las policies de catálogo (Direccion/Responsable/TipoCombustible/Unidad) heredan su
 * comportamiento de CatalogoPolicy sin override, por lo que un solo caso cubre a las
 * cuatro. Operador y User sí tienen reglas propias y se prueban aparte.
 */
class PoliciesTest extends TestCase
{
    private function usuario(Rol $rol): User
    {
        return User::factory()->make(['rol' => $rol]);
    }

    public static function catalogosSinExcepcion(): array
    {
        return [
            'Direccion' => [DireccionPolicy::class, new Direccion()],
            'Responsable' => [ResponsablePolicy::class, new Responsable()],
            'TipoCombustible' => [TipoCombustiblePolicy::class, new TipoCombustible()],
            'Unidad' => [UnidadPolicy::class, new Unidad()],
        ];
    }

    #[DataProvider('catalogosSinExcepcion')]
    public function test_catalogo_todos_consultan_solo_admin_edita_nadie_borra(string $policyClass, Model $modelo): void
    {
        $policy = new $policyClass();
        $admin = $this->usuario(Rol::Admin);
        $capturista = $this->usuario(Rol::Capturista);
        $consulta = $this->usuario(Rol::Consulta);

        foreach ([$admin, $capturista, $consulta] as $user) {
            $this->assertTrue($policy->viewAny($user));
            $this->assertTrue($policy->view($user, $modelo));
        }

        $this->assertTrue($policy->create($admin));
        $this->assertFalse($policy->create($capturista));
        $this->assertFalse($policy->create($consulta));

        $this->assertTrue($policy->update($admin, $modelo));
        $this->assertFalse($policy->update($capturista, $modelo));
        $this->assertFalse($policy->update($consulta, $modelo));

        foreach ([$admin, $capturista, $consulta] as $user) {
            $this->assertFalse($policy->delete($user, $modelo));
            $this->assertFalse($policy->deleteAny($user));
        }
    }

    public function test_operador_tambien_lo_puede_crear_el_capturista(): void
    {
        $policy = new OperadorPolicy();

        $this->assertTrue($policy->create($this->usuario(Rol::Admin)));
        $this->assertTrue($policy->create($this->usuario(Rol::Capturista)));
        $this->assertFalse($policy->create($this->usuario(Rol::Consulta)));

        // view/update/delete no tienen override: siguen el comportamiento de CatalogoPolicy.
        $operador = new Operador();
        $this->assertTrue($policy->update($this->usuario(Rol::Admin), $operador));
        $this->assertFalse($policy->update($this->usuario(Rol::Capturista), $operador));
        $this->assertFalse($policy->delete($this->usuario(Rol::Admin), $operador));
    }

    public function test_usuario_policy_solo_admin_administra_usuarios_y_nadie_borra(): void
    {
        $policy = new UserPolicy();
        $admin = $this->usuario(Rol::Admin);
        $consulta = $this->usuario(Rol::Consulta);
        $otro = $this->usuario(Rol::Consulta);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $otro));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $otro));

        $this->assertFalse($policy->viewAny($consulta));
        $this->assertFalse($policy->view($consulta, $otro));
        $this->assertFalse($policy->create($consulta));
        $this->assertFalse($policy->update($consulta, $otro));

        $this->assertFalse($policy->delete($admin, $otro));
        $this->assertFalse($policy->deleteAny($admin));
    }
}
