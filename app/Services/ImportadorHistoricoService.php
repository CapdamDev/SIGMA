<?php

namespace App\Services;

use App\Enums\ClaseUnidad;
use App\Enums\EstatusUnidad;
use App\Enums\EstatusVale;
use App\Models\Direccion;
use App\Models\Operador;
use App\Models\Responsable;
use App\Models\TipoCombustible;
use App\Models\Unidad;
use App\Models\Vale;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

/**
 * Importa el histórico de Control_Flotillas.xlsx (Google Sheets) y lo limpia.
 * Usada tanto por `php artisan combustible:importar` como por el panel de administración
 * (App\Filament\Pages\ImportarHistorico), para no duplicar la lógica de mapeo/limpieza.
 *
 * Todo corre en una transacción: si algo truena, no queda nada a medias.
 * Lo que no se pudo mapear se escribe en storage/app/importacion/.
 */
class ImportadorHistoricoService
{
    /**
     * IDs de la hoja usuarios_responsables que NO son personas (lugares o conceptos).
     * Revisa esta lista contra tu hoja antes de importar.
     */
    private const RESPONSABLES_NO_PERSONAS = [8, 9, 15, 16, 27, 28, 29, 30, 31, 32];

    /** Responsables duplicados: [id_duplicado => id_que_se_conserva]. */
    private const RESPONSABLES_FUSIONAR = [12 => 10];

    /** Texto que se elimina del nombre del responsable (áreas pegadas al nombre). */
    private const RESPONSABLES_SUFIJOS = [' OPERACIONES', 'COMERCIALIZACION '];

    /** Números económicos que son conceptos de gasto, no unidades físicas. */
    private const CONCEPTOS = ['SINIESTROS', 'SINDICATO', 'CONAGUA'];

    private const VALORES_VACIOS = ['', '0', 'N/A', 'NA', 'N/S', 'S/P', 'N/F', '-'];

    /** @var array<int, array{hoja: string, fila: int, motivo: string, datos: string}> */
    private array $reporte = [];

    /** @var array<int, int> id de hoja => id nuevo */
    private array $mapaDirecciones = [];

    /** @var array<int, int|null> */
    private array $mapaResponsables = [];

    /** @var array<int, int> id numérico de la hoja => id nuevo (solo IDs sin ambigüedad) */
    private array $mapaUnidadesPorId = [];

    /** @var array<int, true> IDs de la hoja que aparecen repetidos */
    private array $idsUnidadAmbiguos = [];

    /** @var array<string, int> número económico normalizado => id nuevo */
    private array $mapaUnidadesPorEco = [];

    /** @var array<string, int> */
    private array $tiposCombustible = [];

    /** @var array<string, int> */
    private array $operadores = [];

    /**
     * Caché de direccionPorNombre(), por instancia (no por proceso): un worker
     * de PHP-FPM reutiliza la misma instancia de PHP entre peticiones, así que
     * una caché a nivel de función quedaría obsoleta entre una importación y otra.
     *
     * @var array<string, int>|null
     */
    private ?array $direccionesPorNombre = null;

    /**
     * @param  ?callable(int $procesado, int $total): void  $onProgress
     * @return array{direcciones: int, responsables: int, unidades: int, operadores: int, importados: int, omitidos: int, avisos: int, reporte_csv: ?string}
     *
     * @throws RuntimeException si el archivo no existe, faltan hojas, ya hay vales sin --fresh, o la importación falla.
     */
    public function ejecutar(string $archivo, bool $dryRun = false, bool $fresh = false, ?callable $onProgress = null): array
    {
        if (! is_file($archivo)) {
            throw new RuntimeException("No encuentro el archivo: {$archivo}");
        }

        if (! $fresh && Vale::query()->exists()) {
            throw new RuntimeException('Ya hay vales en la base de datos. Activa "reemplazar todo" para sustituirlos (borra catálogos, unidades y vales).');
        }

        $hojas = $this->leerHojas($archivo, ['direcciones', 'usuarios_responsables', 'unidades', 'vales_gasolina']);

        foreach (['direcciones', 'usuarios_responsables', 'unidades', 'vales_gasolina'] as $requerida) {
            if (! isset($hojas[$requerida])) {
                throw new RuntimeException("Falta la hoja \"{$requerida}\" en el archivo.");
            }
        }

        if ($fresh && ! $dryRun) {
            $this->vaciarTablas();
        }

        DB::beginTransaction();

        try {
            $this->tiposCombustible = TipoCombustible::query()->pluck('id', 'clave')->all();
            $this->importarDirecciones($hojas['direcciones']);
            $this->importarResponsables($hojas['usuarios_responsables']);
            $this->importarUnidades($hojas['unidades']);
            [$importados, $omitidos] = $this->importarVales($hojas['vales_gasolina'], $onProgress);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();

            throw new RuntimeException('La importación falló y se revirtió: '.$e->getMessage(), previous: $e);
        }

        return [
            'direcciones' => count($this->mapaDirecciones),
            'responsables' => count(array_unique(array_filter($this->mapaResponsables))),
            'unidades' => count($this->mapaUnidadesPorEco),
            'operadores' => count($this->operadores),
            'importados' => $importados,
            'omitidos' => $omitidos,
            'avisos' => count($this->reporte),
            'reporte_csv' => $this->reporte ? $this->escribirReporte() : null,
        ];
    }

    // ------------------------------------------------------------------
    // Lectura
    // ------------------------------------------------------------------

    /**
     * @return array<string, array<int, array<string, mixed>>> hoja => filas (clave = encabezado), índice = número de fila en Excel
     */
    private function leerHojas(string $archivo, array $nombres): array
    {
        $reader = new Reader;
        $reader->open($archivo);
        $resultado = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $nombre = trim($sheet->getName());

            if (! in_array($nombre, $nombres, true)) {
                continue;
            }

            $encabezados = null;
            $filas = [];

            foreach ($sheet->getRowIterator() as $numero => $row) {
                $valores = $row->toArray();

                if ($encabezados === null) {
                    $encabezados = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $valores);

                    continue;
                }

                $fila = [];
                foreach ($encabezados as $i => $encabezado) {
                    if (is_string($encabezado) && $encabezado !== '') {
                        $fila[$encabezado] = $valores[$i] ?? null;
                    }
                }

                if (array_filter($fila, fn ($v) => $v !== null && $v !== '') !== []) {
                    $filas[$numero] = $fila;
                }
            }

            $resultado[$nombre] = $filas;
        }

        $reader->close();

        return $resultado;
    }

    private function vaciarTablas(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['vales_combustible', 'unidades', 'operadores', 'responsables', 'direcciones'] as $tabla) {
            DB::table($tabla)->truncate();
        }
        Schema::enableForeignKeyConstraints();
    }

    // ------------------------------------------------------------------
    // Catálogos
    // ------------------------------------------------------------------

    private function importarDirecciones(array $filas): void
    {
        foreach ($filas as $numero => $fila) {
            $idHoja = $this->entero($fila['id_direccion'] ?? null);
            $nombre = $this->texto($fila['nombre_direccion'] ?? null);

            if (! $idHoja || ! $nombre) {
                $this->avisar('direcciones', $numero, 'Fila sin id o sin nombre', $fila);

                continue;
            }

            $this->mapaDirecciones[$idHoja] = Direccion::firstOrCreate(['nombre' => $nombre])->id;
        }
    }

    private function importarResponsables(array $filas): void
    {
        foreach ($filas as $numero => $fila) {
            $idHoja = $this->entero($fila['id_usuario'] ?? null);
            $nombre = $this->texto($fila['nombre_completo'] ?? null);

            if (! $idHoja || ! $nombre) {
                continue;
            }

            if (in_array($idHoja, self::RESPONSABLES_NO_PERSONAS, true)) {
                $this->mapaResponsables[$idHoja] = null;
                $this->avisar('usuarios_responsables', $numero, 'No es una persona: se omite del catálogo (las unidades quedan sin responsable)', $fila);

                continue;
            }

            if (isset(self::RESPONSABLES_FUSIONAR[$idHoja])) {
                continue; // se resuelve al final
            }

            $nombre = mb_strtoupper($nombre);
            foreach (self::RESPONSABLES_SUFIJOS as $sufijo) {
                $nombre = trim(str_replace($sufijo, ' ', $nombre));
            }

            $titulo = null;
            if (preg_match('/^(ING\.|ARQ\.|LIC\.|C\.\s?P\.|C\.)\s*(.+)$/u', $nombre, $m)) {
                $titulo = str_replace(' ', '', $m[1]);
                $nombre = trim($m[2]);
            }

            $this->mapaResponsables[$idHoja] = Responsable::create([
                'titulo' => $titulo,
                'nombre' => Str::title($nombre),
            ])->id;
        }

        foreach (self::RESPONSABLES_FUSIONAR as $duplicado => $conservado) {
            $this->mapaResponsables[$duplicado] = $this->mapaResponsables[$conservado] ?? null;
        }
    }

    // ------------------------------------------------------------------
    // Unidades
    // ------------------------------------------------------------------

    private function importarUnidades(array $filas): void
    {
        $vistos = [];

        foreach ($filas as $numero => $fila) {
            $eco = $this->normalizarEco($fila['eco_nombre'] ?? null);

            if (! $eco) {
                continue; // filas de relleno con solo id
            }

            if (isset($this->mapaUnidadesPorEco[$eco])) {
                $this->avisar('unidades', $numero, "Número económico repetido ({$eco}): se conserva el primero", $fila);

                continue;
            }

            $descripcion = $this->texto($fila['descripcion'] ?? null);
            $capacidadCruda = $fila['capacidad_tanque'] ?? null;
            $clase = $this->deducirClase($eco, (string) $descripcion);
            $estatus = EstatusUnidad::Activa;

            if (is_string($capacidadCruda) && Str::contains(Str::lower($capacidadCruda), 'fuera de operaci')) {
                $estatus = EstatusUnidad::FueraOperacion;
            }
            if ($descripcion && Str::contains(Str::upper($descripcion), 'TRAMITE DE BAJA')) {
                $estatus = EstatusUnidad::TramiteBaja;
            }

            $capacidad = $this->decimal($capacidadCruda);
            if ($capacidad !== null && ($capacidad <= 0 || $capacidad > 2000)) {
                $this->avisar('unidades', $numero, "Capacidad de tanque inválida ({$this->resumirValor($capacidadCruda)}): se deja vacía", $fila);
                $capacidad = null;
            } elseif ($capacidad === null && $this->texto($capacidadCruda) && $estatus === EstatusUnidad::Activa) {
                $this->avisar('unidades', $numero, "Capacidad de tanque no numérica ({$this->resumirValor($capacidadCruda)}): se deja vacía", $fila);
            }

            $rendimiento = $this->decimal($fila['rendimiento_km_litro'] ?? null);
            if ($rendimiento !== null && ($rendimiento <= 0 || $rendimiento > 100)) {
                $this->avisar('unidades', $numero, "Rendimiento inválido ({$this->resumirValor($fila['rendimiento_km_litro'])}): se deja vacío", $fila);
                $rendimiento = null;
            }

            $anio = $this->entero($fila['modelo'] ?? null);
            $idDireccionHoja = $this->entero($fila['id_direccion'] ?? null);
            $idResponsableHoja = $this->entero($fila['id_usuario_responsable'] ?? null);

            if ($idDireccionHoja && ! isset($this->mapaDirecciones[$idDireccionHoja])) {
                $this->avisar('unidades', $numero, "id_direccion {$idDireccionHoja} no existe en la hoja direcciones", $fila);
            }

            $unidad = Unidad::create([
                'numero_economico' => $eco,
                'clase' => $clase,
                'descripcion' => $descripcion ? Str::upper(preg_replace('/\s+/', ' ', $descripcion)) : null,
                'marca' => $this->textoUtil($fila['marca'] ?? null),
                'anio_modelo' => ($anio >= 1950 && $anio <= 2100) ? $anio : null,
                'placa' => $this->textoUtil($fila['placa'] ?? null),
                'tipo_combustible_id' => $this->tipoCombustibleId($fila['tipo_combustible'] ?? null),
                'capacidad_tanque_l' => $capacidad,
                'rendimiento_esperado' => $rendimiento,
                'tipo_medidor' => $clase->medidorPorDefecto(),
                'estatus' => $estatus,
                'direccion_id' => $this->mapaDirecciones[$idDireccionHoja] ?? null,
                'responsable_id' => $this->mapaResponsables[$idResponsableHoja] ?? null,
            ]);

            $this->mapaUnidadesPorEco[$eco] = $unidad->id;

            $idHoja = $this->entero($fila['Columna 1'] ?? null);
            if ($idHoja) {
                if (isset($vistos[$idHoja])) {
                    $this->idsUnidadAmbiguos[$idHoja] = true;
                    $this->avisar('unidades', $numero, "El id {$idHoja} también lo usa {$vistos[$idHoja]}: los vales con ese id se omiten", $fila);
                } else {
                    $vistos[$idHoja] = $eco;
                    $this->mapaUnidadesPorId[$idHoja] = $unidad->id;
                }
            }
        }

        foreach (array_keys($this->idsUnidadAmbiguos) as $id) {
            unset($this->mapaUnidadesPorId[$id]);
        }
    }

    private function deducirClase(string $eco, string $descripcion): ClaseUnidad
    {
        $descripcion = Str::upper($descripcion);

        return match (true) {
            in_array($eco, self::CONCEPTOS, true) => ClaseUnidad::Concepto,
            str_starts_with($eco, 'BID') => ClaseUnidad::Bidon,
            str_starts_with($eco, 'G ') => ClaseUnidad::Generador,
            Str::contains($descripcion, 'MOTOCICLETA') => ClaseUnidad::Motocicleta,
            Str::contains($descripcion, ['RETRO', 'MINICARGA', 'MINICARCAGOR']) => ClaseUnidad::Maquinaria,
            Str::contains($descripcion, ['PIPA', 'VACTOR', 'VOLTEO', 'DESASOLVE', 'DESAZOLVE', 'GRUA', 'PLATAFORMA']) => ClaseUnidad::Pipa,
            default => ClaseUnidad::Vehiculo,
        };
    }

    // ------------------------------------------------------------------
    // Vales
    // ------------------------------------------------------------------

    /**
     * @param  ?callable(int $procesado, int $total): void  $onProgress
     * @return array{0: int, 1: int}
     */
    private function importarVales(array $filas, ?callable $onProgress = null): array
    {
        $importados = 0;
        $omitidos = 0;
        $procesado = 0;
        $total = count($filas);

        foreach ($filas as $numero => $fila) {
            $procesado++;
            if ($onProgress) {
                $onProgress($procesado, $total);
            }

            $unidadId = $this->resolverUnidad($fila['id_unidad'] ?? null, $numero, $fila);
            if (! $unidadId) {
                $this->avisar('vales_gasolina', $numero, 'Unidad no encontrada o ambigua ('.$this->resumirValor($fila['id_unidad'] ?? null).')', $fila);
                $omitidos++;

                continue;
            }

            $fecha = $this->fecha($fila['fecha_carga'] ?? null) ?? $this->fecha($fila['Columna 1'] ?? null);
            if (! $fecha) {
                $this->avisar('vales_gasolina', $numero, 'Sin fecha de carga válida', $fila);
                $omitidos++;

                continue;
            }

            $litros = $this->decimal($fila['litros'] ?? null);
            $importe = $this->decimal($fila['total_dinero'] ?? null);
            if (! $litros || $litros <= 0 || ! $importe || $importe <= 0) {
                $this->avisar('vales_gasolina', $numero, 'Litros o importe vacíos o inválidos', $fila);
                $omitidos++;

                continue;
            }

            if ($litros > 2000 || $importe > 200000) {
                $this->avisar('vales_gasolina', $numero, 'Litros o importe fuera de rango (¿error de captura?): se omite', $fila);
                $omitidos++;

                continue;
            }

            $precio = $importe / $litros;
            if ($precio < 10 || $precio > 50) {
                $this->avisar('vales_gasolina', $numero, sprintf('Precio por litro atípico ($%.2f): se importa, pero revísalo', $precio), $fila);
            }

            // folio_vale: 0 = sin folio, número = folio, texto = nombre del operador
            $folio = null;
            $operadorId = null;
            $folioCrudo = $fila['folio_vale'] ?? null;
            if (is_numeric($folioCrudo)) {
                $folio = ((float) $folioCrudo) > 0 ? (string) (int) $folioCrudo : null;
            } elseif ($nombre = $this->texto($folioCrudo)) {
                $operadorId = $this->operadorId($nombre);
            }

            $observaciones = [];
            if ($obs = $this->texto($fila['observaciones'] ?? null)) {
                $observaciones[] = $obs;
            }

            [$lectura, $medidorValido, $notaMedidor] = $this->lectura($fila['km_carga'] ?? null, $obs);
            if ($notaMedidor) {
                $observaciones[] = $notaMedidor;
            }

            $tipo = $this->tipoCombustibleId($fila['tipo_combustible'] ?? null)
                ?? Unidad::query()->whereKey($unidadId)->value('tipo_combustible_id');
            if (! $tipo) {
                $this->avisar('vales_gasolina', $numero, 'Sin tipo de combustible en el vale ni en la unidad', $fila);
                $omitidos++;

                continue;
            }

            $capturado = $this->fecha($fila['Columna 1'] ?? null) ?? $fecha;
            $direccionId = $this->direccionPorNombre($fila['departamento'] ?? null);

            $vale = new Vale([
                'folio' => $folio,
                'unidad_id' => $unidadId,
                'operador_id' => $operadorId,
                'direccion_id' => $direccionId,
                'tipo_combustible_id' => $tipo,
                'fecha_carga' => $fecha,
                'lectura_medidor' => $lectura,
                'medidor_valido' => $medidorValido,
                'litros' => round($litros, 2),
                'importe' => round($importe, 2),
                'observaciones' => $observaciones ? Str::limit(implode(' | ', $observaciones), 497) : null,
                'estatus' => EstatusVale::Registrado,
            ]);
            $vale->created_at = $capturado;
            $vale->updated_at = $capturado;
            $vale->save();

            $importados++;
        }

        return [$importados, $omitidos];
    }

    private function resolverUnidad(mixed $valor, int $numero, array $fila): ?int
    {
        if (is_numeric($valor)) {
            $id = (int) $valor;

            if (isset($this->mapaUnidadesPorId[$id])) {
                return $this->mapaUnidadesPorId[$id];
            }

            // Unidades capturadas sin id en la hoja: se intenta por número económico.
            $eco = sprintf('ECO %03d', $id);
            if (! isset($this->idsUnidadAmbiguos[$id]) && isset($this->mapaUnidadesPorEco[$eco])) {
                $this->avisar('vales_gasolina', $numero, "id_unidad {$id} no existe en la hoja unidades: se asignó a {$eco}, verifícalo", $fila);

                return $this->mapaUnidadesPorEco[$eco];
            }

            return null;
        }

        $eco = $this->normalizarEco($valor);

        return $eco ? ($this->mapaUnidadesPorEco[$eco] ?? null) : null;
    }

    /** @return array{0: float|null, 1: bool, 2: string|null} [lectura, medidor válido, nota] */
    private function lectura(mixed $valor, ?string $observaciones): array
    {
        $noFunciona = $observaciones && preg_match('/(km|kilometraje).{0,5}(no funciona|n\/f)|n\/f.{0,5}(km|kilometraje)/iu', $observaciones);

        if (is_numeric($valor) && (float) $valor > 0 && ! $noFunciona) {
            return [round((float) $valor, 1), true, null];
        }

        if (is_string($valor) && preg_match('/^\s*(\d{2,})/', $valor, $m) && ! $noFunciona) {
            return [(float) $m[1], true, "Lectura original: {$valor}"];
        }

        $nota = is_string($valor) && trim($valor) !== '' ? "Lectura original: {$valor}" : null;

        return [null, false, $nota];
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    private function operadorId(string $nombre): int
    {
        $clave = Str::upper(Str::squish($nombre));

        return $this->operadores[$clave] ??= Operador::create(['nombre' => Str::title($clave)])->id;
    }

    private function tipoCombustibleId(mixed $valor): ?int
    {
        $clave = Str::upper(trim((string) $valor));

        return $clave !== '' ? ($this->tiposCombustible[$clave] ?? null) : null;
    }

    private function direccionPorNombre(mixed $valor): ?int
    {
        $this->direccionesPorNombre ??= Direccion::query()->pluck('id', 'nombre')->mapWithKeys(fn ($id, $n) => [Str::lower($n) => $id])->all();
        $nombre = Str::lower(trim((string) $valor));

        return $nombre !== '' ? ($this->direccionesPorNombre[$nombre] ?? null) : null;
    }

    private function normalizarEco(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        if (! $texto) {
            return null;
        }

        $texto = Str::upper(Str::squish($texto));

        // "G003", "BID 03", "ECO 5" => "G 003", "BID 003", "ECO 005"
        if (preg_match('/^([A-Z]+)\s*(\d{1,3})$/', $texto, $m)) {
            return sprintf('%s %03d', $m[1], (int) $m[2]);
        }

        return $texto;
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null || $valor instanceof DateTimeInterface) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /** Texto que además no sea un marcador de vacío como "N/A" o "S/P". */
    private function textoUtil(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        return ($texto === null || in_array(Str::upper($texto), self::VALORES_VACIOS, true)) ? null : $texto;
    }

    private function entero(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }

    private function decimal(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        if (is_string($valor)) {
            $limpio = rtrim(str_replace([',', '$', ' '], '', $valor), '.');

            return is_numeric($limpio) ? (float) $limpio : null;
        }

        return null; // fechas y demás
    }

    private function fecha(mixed $valor): ?Carbon
    {
        if ($valor instanceof DateTimeInterface) {
            return Carbon::instance($valor)->shiftTimezone(config('app.timezone'));
        }

        if (is_numeric($valor) && $valor > 30000 && $valor < 80000) {
            // Número de serie de Excel
            return Carbon::create(1899, 12, 30, 0, 0, 0, config('app.timezone'))->addSeconds((int) round($valor * 86400));
        }

        if (is_string($valor) && trim($valor) !== '') {
            try {
                return Carbon::parse($valor, config('app.timezone'));
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function resumirValor(mixed $valor): string
    {
        return match (true) {
            $valor === null => 'vacío',
            $valor instanceof DateTimeInterface => 'fecha '.$valor->format('Y-m-d'),
            default => (string) $valor,
        };
    }

    private function avisar(string $hoja, int $fila, string $motivo, array $datos): void
    {
        $this->reporte[] = [
            'hoja' => $hoja,
            'fila' => $fila,
            'motivo' => $motivo,
            'datos' => json_encode(array_map(fn ($v) => $v instanceof DateTimeInterface ? $v->format('Y-m-d H:i') : $v, $datos), JSON_UNESCAPED_UNICODE),
        ];
    }

    private function escribirReporte(): string
    {
        $dir = storage_path('app/importacion');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ruta = $dir.'/reporte_'.now()->format('Ymd_His').'.csv';
        $fp = fopen($ruta, 'w');
        fwrite($fp, "\xEF\xBB\xBF"); // BOM para que Excel respete los acentos
        fputcsv($fp, ['hoja', 'fila', 'motivo', 'datos']);
        foreach ($this->reporte as $linea) {
            fputcsv($fp, $linea);
        }
        fclose($fp);

        return $ruta;
    }
}
