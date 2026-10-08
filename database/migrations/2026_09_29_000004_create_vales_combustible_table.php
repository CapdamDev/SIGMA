<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vales_combustible', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 30)->nullable()->index();   // sin UNIQUE: el histórico trae folios repetidos
            $table->foreignId('unidad_id')->constrained('unidades');
            $table->foreignId('operador_id')->nullable()->constrained('operadores');
            // Snapshot de la asignación al momento de la carga
            $table->foreignId('direccion_id')->nullable()->constrained('direcciones');
            $table->foreignId('responsable_id')->nullable()->constrained('responsables');
            $table->foreignId('tipo_combustible_id')->constrained('tipos_combustible');
            $table->dateTime('fecha_carga');
            $table->decimal('lectura_medidor', 10, 1)->nullable();  // km u horas
            $table->boolean('medidor_valido')->default(true);        // false = "km no funciona"
            $table->decimal('litros', 8, 2);
            $table->decimal('importe', 10, 2);
            $table->decimal('precio_litro', 8, 3)->storedAs('ROUND(importe / NULLIF(litros, 0), 3)');
            $table->string('observaciones', 500)->nullable();
            $table->string('estatus', 20)->default('registrado');    // App\Enums\EstatusVale
            $table->string('motivo_cancelacion')->nullable();
            $table->foreignId('capturado_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['unidad_id', 'fecha_carga']);
            $table->index(['direccion_id', 'fecha_carga']);
            $table->index('fecha_carga');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vales_combustible');
    }
};
