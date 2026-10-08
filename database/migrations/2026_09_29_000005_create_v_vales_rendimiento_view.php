<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rendimiento por vale: compara cada lectura válida contra la lectura válida
 * anterior de la MISMA unidad, ordenada por fecha de carga (no por captura).
 * Requiere MySQL 8+ o MariaDB 10.2+ (CTE + funciones de ventana).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_vales_rendimiento AS
            WITH validos AS (
                SELECT
                    v.id,
                    v.litros,
                    v.lectura_medidor,
                    LAG(v.lectura_medidor) OVER (
                        PARTITION BY v.unidad_id ORDER BY v.fecha_carga, v.id
                    ) AS lectura_anterior
                FROM vales_combustible v
                WHERE v.estatus = 'registrado'
                  AND v.medidor_valido = 1
                  AND v.lectura_medidor IS NOT NULL
            )
            SELECT
                id,
                lectura_anterior,
                CASE WHEN lectura_medidor > lectura_anterior
                     THEN lectura_medidor - lectura_anterior END AS recorrido,
                CASE WHEN lectura_medidor > lectura_anterior
                     THEN ROUND((lectura_medidor - lectura_anterior) / litros, 2) END AS rendimiento,
                CASE WHEN lectura_anterior IS NOT NULL AND lectura_medidor <= lectura_anterior
                     THEN 1 ELSE 0 END AS lectura_sospechosa
            FROM validos
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_vales_rendimiento');
    }
};
