<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direcciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Solo personas. Lugares y conceptos ("Corralón", "Equipos auxiliares")
        // viven en direcciones o como clase de unidad.
        Schema::create('responsables', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 20)->nullable();
            $table->string('nombre', 150);
            $table->foreignId('direccion_id')->nullable()->constrained('direcciones');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Quien recibe físicamente la carga.
        Schema::create('operadores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('numero_empleado', 20)->nullable()->unique();
            $table->foreignId('direccion_id')->nullable()->constrained('direcciones');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('tipos_combustible', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 20)->unique();
            $table->string('nombre', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_combustible');
        Schema::dropIfExists('operadores');
        Schema::dropIfExists('responsables');
        Schema::dropIfExists('direcciones');
    }
};
