<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El histórico trae unidades sin número económico real, capturadas con
     * etiquetas tipo "UNIDAD SIN NOMBRE 138" que no entran en 20 caracteres.
     */
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            // El índice único ya existe: solo se cambia el tipo/longitud, no se
            // vuelve a declarar ->unique() o Laravel intenta crear el índice otra vez.
            $table->string('numero_economico', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->string('numero_economico', 20)->change();
        });
    }
};
