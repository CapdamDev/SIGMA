<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Filament\Pages\ImportarHistorico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImportarHistoricoPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_ver_la_pagina(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::Admin]));

        Livewire::test(ImportarHistorico::class)->assertOk();
    }

    public function test_capturista_no_puede_ver_la_pagina(): void
    {
        User::factory()->create(); // el primero: se vuelve admin solo, por diseño
        $this->actingAs(User::factory()->create(['rol' => Rol::Capturista]));

        $this->get(ImportarHistorico::getUrl())->assertForbidden();
    }

    public function test_consulta_no_puede_ver_la_pagina(): void
    {
        User::factory()->create(); // el primero: se vuelve admin solo, por diseño
        $this->actingAs(User::factory()->create(['rol' => Rol::Consulta]));

        $this->get(ImportarHistorico::getUrl())->assertForbidden();
    }
}
