<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('numero_economico', 20)->unique();          // ECO 001, BID 014, G 003
            $table->string('clase', 20)->default('vehiculo');          // App\Enums\ClaseUnidad
            $table->string('descripcion')->nullable();
            $table->string('marca', 50)->nullable();
            $table->unsignedSmallInteger('anio_modelo')->nullable();
            $table->string('placa', 15)->nullable()->index();
            $table->foreignId('tipo_combustible_id')->nullable()->constrained('tipos_combustible');
            $table->decimal('capacidad_tanque_l', 7, 2)->nullable();
            $table->decimal('rendimiento_esperado', 5, 2)->nullable();  // km/L u horas/L
            $table->string('tipo_medidor', 10)->default('km');          // App\Enums\TipoMedidor
            $table->string('estatus', 20)->default('activa');          // App\Enums\EstatusUnidad
            // Asignación ACTUAL. El histórico vive en cada vale.
            $table->foreignId('direccion_id')->nullable()->constrained('direcciones');
            $table->foreignId('responsable_id')->nullable()->constrained('responsables');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
