<?php

namespace App\Exports;

use App\Models\Vale;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta a Excel exactamente lo que el usuario tiene filtrado en la tabla de vales.
 * Síncrono (no requiere colas); para unos miles de filas es instantáneo.
 */
class ValesXlsx
{
    private const ENCABEZADOS = [
        'ID', 'Folio', 'Fecha de carga', 'Unidad', 'Descripción', 'Placa', 'Dirección',
        'Responsable', 'Operador', 'Combustible', 'Lectura medidor', 'Medidor válido',
        'Recorrido', 'Rendimiento', 'Litros', 'Importe', 'Precio por litro',
        'Estatus', 'Observaciones', 'Capturado por', 'Fecha de captura',
    ];

    public static function descargar(Builder $query, ?string $nombre = null): StreamedResponse
    {
        $nombre ??= 'vales_combustible_'.now()->format('Ymd_His').'.xlsx';
        $base = tempnam(sys_get_temp_dir(), 'vales_');
        @unlink($base);
        $ruta = $base.'.xlsx';

        $opciones = new Options;
        $opciones->setColumnWidth(12, 1, 2);
        $opciones->setColumnWidth(18, 3);
        $opciones->setColumnWidth(12, 4);
        $opciones->setColumnWidth(32, 5, 7, 8, 9, 19);

        $writer = new Writer($opciones);
        $writer->openToFile($ruta);
        $writer->addRow(Row::fromValues(self::ENCABEZADOS, (new Style)->setFontBold()));

        $query
            ->with(['unidad', 'direccion', 'responsable', 'operador', 'tipoCombustible', 'rendimiento', 'capturadoPor'])
            ->reorder('fecha_carga')
            ->orderBy('id')
            ->chunk(1000, function ($vales) use ($writer) {
                /** @var Vale $vale */
                foreach ($vales as $vale) {
                    $writer->addRow(Row::fromValues([
                        $vale->id,
                        $vale->folio,
                        $vale->fecha_carga?->toDateTimeImmutable(),
                        $vale->unidad?->numero_economico,
                        $vale->unidad?->descripcion,
                        $vale->unidad?->placa,
                        $vale->direccion?->nombre,
                        $vale->responsable?->nombre_completo,
                        $vale->operador?->nombre,
                        $vale->tipoCombustible?->clave,
                        self::num($vale->lectura_medidor),
                        $vale->medidor_valido ? 'Sí' : 'No',
                        self::num($vale->rendimiento?->recorrido),
                        self::num($vale->rendimiento?->rendimiento),
                        self::num($vale->litros),
                        self::num($vale->importe),
                        self::num($vale->precio_litro),
                        $vale->estatus?->getLabel(),
                        $vale->observaciones,
                        $vale->capturadoPor?->name,
                        $vale->created_at?->toDateTimeImmutable(),
                    ]));
                }
            });

        $writer->close();

        return response()->streamDownload(function () use ($ruta) {
            readfile($ruta);
            @unlink($ruta);
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function num(mixed $valor): ?float
    {
        return $valor === null ? null : (float) $valor;
    }
}
