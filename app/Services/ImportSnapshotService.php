<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Respaldo y restauración de las tablas que toca la importación del histórico
 * (las mismas que vacía su modo --fresh), para poder deshacer una importación
 * que salió mal desde el panel de administración.
 *
 * Se guarda como JSON en el disco "local" (storage/app/private, no público)
 * en vez de un dump de MySQL: así no depende de tener mysqldump instalado
 * en el contenedor de la app.
 */
class ImportSnapshotService
{
    /** Orden de dependencia: padres primero. */
    private const TABLAS = ['direcciones', 'responsables', 'operadores', 'unidades', 'vales_combustible'];

    /**
     * Columnas generadas por la base de datos: nunca se insertan directamente.
     * vales_combustible.precio_litro es STORED GENERATED (ROUND(importe/litros, 3)).
     */
    private const COLUMNAS_GENERADAS = [
        'vales_combustible' => ['precio_litro'],
    ];

    private const DIRECTORIO = 'importacion/respaldos';

    private const DISCO = 'local';

    public function crear(string $motivo = ''): string
    {
        $archivo = now()->format('Ymd_His_u').'.json';

        $datos = [
            'creado_en' => now()->toIso8601String(),
            'motivo' => $motivo,
            'tablas' => [],
        ];

        foreach (self::TABLAS as $tabla) {
            $datos['tablas'][$tabla] = DB::table($tabla)->get()->map(fn ($fila) => (array) $fila)->all();
        }

        Storage::disk(self::DISCO)->put(
            self::DIRECTORIO.'/'.$archivo,
            json_encode($datos, JSON_UNESCAPED_UNICODE)
        );

        return $archivo;
    }

    /**
     * @return array<int, array{archivo: string, creado_en: string, motivo: string, tamano: int}>
     */
    public function listar(): array
    {
        $disco = Storage::disk(self::DISCO);

        if (! $disco->exists(self::DIRECTORIO)) {
            return [];
        }

        $snapshots = [];
        foreach ($disco->files(self::DIRECTORIO) as $ruta) {
            $datos = json_decode($disco->get($ruta), true);

            $snapshots[] = [
                'archivo' => basename($ruta),
                'creado_en' => $datos['creado_en'] ?? '',
                'motivo' => $datos['motivo'] ?? '',
                'tamano' => $disco->size($ruta),
            ];
        }

        // Se ordena por nombre de archivo (Ymd_His_u), no por creado_en: este último
        // solo tiene resolución de segundos y dos respaldos en el mismo segundo
        // quedarían en orden arbitrario.
        usort($snapshots, fn ($a, $b) => $b['archivo'] <=> $a['archivo']);

        return $snapshots;
    }

    public function restaurar(string $archivo): void
    {
        $disco = Storage::disk(self::DISCO);
        $ruta = self::DIRECTORIO.'/'.basename($archivo);

        if (! $disco->exists($ruta)) {
            throw new RuntimeException("No encuentro el respaldo: {$archivo}");
        }

        $datos = json_decode($disco->get($ruta), true);

        if (! isset($datos['tablas'])) {
            throw new RuntimeException("El respaldo {$archivo} está corrupto o incompleto.");
        }

        DB::transaction(function () use ($datos) {
            Schema::disableForeignKeyConstraints();

            try {
                foreach (self::TABLAS as $tabla) {
                    DB::table($tabla)->truncate();
                }

                foreach (self::TABLAS as $tabla) {
                    $generadas = self::COLUMNAS_GENERADAS[$tabla] ?? [];
                    $filas = array_map(
                        fn (array $fila) => array_diff_key($fila, array_flip($generadas)),
                        $datos['tablas'][$tabla] ?? []
                    );

                    foreach (array_chunk($filas, 500) as $lote) {
                        if ($lote !== []) {
                            DB::table($tabla)->insert($lote);
                        }
                    }
                }
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });
    }

    public function eliminar(string $archivo): void
    {
        Storage::disk(self::DISCO)->delete(self::DIRECTORIO.'/'.basename($archivo));
    }
}
