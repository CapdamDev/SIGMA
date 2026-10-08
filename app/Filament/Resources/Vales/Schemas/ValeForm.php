<?php

namespace App\Filament\Resources\Vales\Schemas;

use App\Enums\EstatusUnidad;
use App\Enums\TipoMedidor;
use App\Filament\Resources\Operadores\OperadorResource;
use App\Models\Responsable;
use App\Models\Unidad;
use App\Models\Vale;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ValeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Unidad')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('unidad_id')
                            ->label('Unidad')
                            ->relationship(
                                'unidad',
                                'numero_economico',
                                fn (Builder $query, string $operation) => $operation === 'create'
                                    ? $query->where('estatus', '!=', EstatusUnidad::Baja)
                                    : $query,
                            )
                            ->getOptionLabelFromRecordUsing(fn (Unidad $record) => $record->etiqueta)
                            ->searchable(['numero_economico', 'descripcion', 'placa'])
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (?string $state, Set $set) => static::alCambiarUnidad($state, $set))
                            ->columnSpanFull(),
                        TextEntry::make('resumen_ultima_carga')
                            ->label('Carga anterior con lectura')
                            ->state(fn (Get $get, ?Vale $record) => static::resumenCargaAnterior($get, $record))
                            ->columnSpanFull(),
                        DateTimePicker::make('fecha_carga')
                            ->label('Fecha y hora de carga')
                            ->seconds(false)
                            ->default(now())
                            ->maxDate(now()->endOfDay())
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('folio')
                            ->maxLength(30),
                        Select::make('operador_id')
                            ->label('Operador que recibió')
                            ->relationship('operador', 'nombre', fn (Builder $query) => $query->where('activo', true))
                            ->searchable()
                            ->preload()
                            ->createOptionForm(OperadorResource::camposFormulario()),
                        Select::make('tipo_combustible_id')
                            ->label('Combustible')
                            ->relationship('tipoCombustible', 'nombre')
                            ->required(),
                    ]),

                Section::make('Carga')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('litros')
                            ->numeric()
                            ->inputMode('decimal')
                            ->minValue(0.01)
                            ->maxValue(2000)
                            ->suffix('L')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get) => ($capacidad = static::unidad($get)?->capacidad_tanque_l)
                                ? 'Capacidad del tanque: '.number_format((float) $capacidad, 0).' L'
                                : null),
                        TextInput::make('importe')
                            ->numeric()
                            ->inputMode('decimal')
                            ->minValue(0.01)
                            ->prefix('$')
                            ->required()
                            ->live(onBlur: true),
                        TextEntry::make('precio_calculado')
                            ->label('Precio por litro')
                            ->state(fn (Get $get) => static::resumenPrecio($get)),
                    ]),

                Section::make('Medidor')
                    ->columnSpan(2)
                    ->columns(2)
                    ->hidden(fn (Get $get) => static::unidad($get)?->tipo_medidor === TipoMedidor::Ninguno)
                    ->schema([
                        Toggle::make('medidor_valido')
                            ->label('El medidor funciona')
                            ->helperText('Desactívalo si el odómetro u horómetro está descompuesto. La carga no contará para el rendimiento.')
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('lectura_medidor')
                            ->label(fn (Get $get) => static::unidad($get)?->tipo_medidor === TipoMedidor::Horas ? 'Lectura del horómetro' : 'Lectura del odómetro')
                            ->numeric()
                            ->inputMode('decimal')
                            ->minValue(0)
                            ->suffix(fn (Get $get) => static::unidad($get)?->tipo_medidor?->sufijo() ?? 'km')
                            ->required(fn (Get $get) => (bool) $get('medidor_valido'))
                            ->disabled(fn (Get $get) => ! $get('medidor_valido'))
                            ->live(onBlur: true)
                            ->rules([
                                fn (Get $get, ?Vale $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                    if ($get('confirmar_lectura')) {
                                        return;
                                    }

                                    if ($problema = static::problemaConLectura($get, $record)) {
                                        $fail($problema.' Corrige el dato o marca la confirmación.');
                                    }
                                },
                            ]),
                        Toggle::make('confirmar_lectura')
                            ->label('Confirmo que la lectura es correcta')
                            ->helperText('Por ejemplo, si se cambió el odómetro. Explica el motivo en observaciones.')
                            ->dehydrated(false)
                            ->live()
                            ->visible(fn (Get $get, ?Vale $record) => static::problemaConLectura($get, $record) !== null),
                    ]),

                Section::make('Asignación')
                    ->description('Se toma de la unidad. Cámbiala solo si la carga fue para otra área.')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('direccion_id')
                            ->label('Dirección')
                            ->relationship('direccion', 'nombre')
                            ->searchable()
                            ->preload(),
                        Select::make('responsable_id')
                            ->label('Responsable')
                            ->relationship('responsable', 'nombre')
                            ->getOptionLabelFromRecordUsing(fn (Responsable $record) => $record->nombre_completo)
                            ->searchable()
                            ->preload(),
                    ]),

                Textarea::make('observaciones')
                    ->maxLength(500)
                    ->rows(2)
                    ->required(fn (Get $get) => (bool) $get('confirmar_lectura'))
                    ->columnSpanFull(),
            ]);
    }

    protected static function alCambiarUnidad(?string $unidadId, Set $set): void
    {
        $unidad = $unidadId ? Unidad::find($unidadId) : null;

        $set('tipo_combustible_id', $unidad?->tipo_combustible_id);
        $set('direccion_id', $unidad?->direccion_id);
        $set('responsable_id', $unidad?->responsable_id);
        $set('medidor_valido', true);
        $set('confirmar_lectura', false);
    }

    protected static function unidad(Get $get): ?Unidad
    {
        static $cache = [];
        $id = $get('unidad_id');

        if (blank($id)) {
            return null;
        }

        return $cache[$id] ??= Unidad::find($id);
    }

    protected static function fecha(Get $get): ?Carbon
    {
        $fecha = $get('fecha_carga');

        return filled($fecha) ? Carbon::parse($fecha) : null;
    }

    protected static function cargaAnterior(Get $get, ?Vale $record): ?Vale
    {
        $unidad = static::unidad($get);

        return $unidad
            ? Vale::cargaAnteriorConLectura($unidad->id, static::fecha($get) ?? now(), $record?->getKey())
            : null;
    }

    protected static function cargaSiguiente(Get $get, ?Vale $record): ?Vale
    {
        $unidad = static::unidad($get);
        $fecha = static::fecha($get);

        if (! $unidad || ! $fecha) {
            return null;
        }

        return Vale::query()
            ->registrados()
            ->where('unidad_id', $unidad->id)
            ->where('medidor_valido', true)
            ->whereNotNull('lectura_medidor')
            ->where('fecha_carga', '>', $fecha)
            ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey()))
            ->orderBy('fecha_carga')
            ->orderBy('id')
            ->first();
    }

    /**
     * Devuelve una descripción del problema si la lectura no cuadra con las
     * cargas vecinas de la unidad, o null si todo está en orden.
     */
    public static function problemaConLectura(Get $get, ?Vale $record): ?string
    {
        $lectura = $get('lectura_medidor');

        if (! $get('medidor_valido') || ! is_numeric($lectura)) {
            return null;
        }

        $lectura = (float) $lectura;
        $unidad = static::unidad($get);
        $sufijo = $unidad?->tipo_medidor?->sufijo() ?? 'km';

        if ($anterior = static::cargaAnterior($get, $record)) {
            $previa = (float) $anterior->lectura_medidor;

            if ($lectura <= $previa) {
                return sprintf(
                    'La lectura no avanza respecto a la carga del %s (%s %s).',
                    $anterior->fecha_carga->format('d/m/Y H:i'),
                    number_format($previa, 0),
                    $sufijo,
                );
            }

            $litros = (float) $get('litros');
            $esperado = (float) $unidad?->rendimiento_esperado;

            if ($litros > 0 && $esperado > 0 && ($lectura - $previa) / $litros > $esperado * 3) {
                return sprintf(
                    'El recorrido de %s %s daría %s %s/L, más del triple de lo esperado (%s). ¿Sobra un dígito?',
                    number_format($lectura - $previa, 0),
                    $sufijo,
                    number_format(($lectura - $previa) / $litros, 1),
                    $sufijo,
                    number_format($esperado, 1),
                );
            }
        }

        if (($siguiente = static::cargaSiguiente($get, $record)) && $lectura >= (float) $siguiente->lectura_medidor) {
            return sprintf(
                'La lectura es mayor que la de una carga posterior (%s, %s %s).',
                $siguiente->fecha_carga->format('d/m/Y H:i'),
                number_format((float) $siguiente->lectura_medidor, 0),
                $sufijo,
            );
        }

        return null;
    }

    protected static function resumenCargaAnterior(Get $get, ?Vale $record): string
    {
        $unidad = static::unidad($get);

        if (! $unidad) {
            return 'Selecciona una unidad.';
        }

        if ($unidad->tipo_medidor === TipoMedidor::Ninguno) {
            return 'Esta unidad no lleva medidor.';
        }

        $anterior = static::cargaAnterior($get, $record);

        if (! $anterior) {
            return 'Sin cargas previas con lectura.';
        }

        return sprintf(
            '%s %s el %s (%s L)',
            number_format((float) $anterior->lectura_medidor, 0),
            $unidad->tipo_medidor->sufijo(),
            $anterior->fecha_carga->format('d/m/Y H:i'),
            number_format((float) $anterior->litros, 2),
        );
    }

    protected static function resumenPrecio(Get $get): string
    {
        $litros = (float) $get('litros');
        $importe = (float) $get('importe');

        if ($litros <= 0 || $importe <= 0) {
            return 'Captura litros e importe.';
        }

        $texto = '$'.number_format($importe / $litros, 2);

        if ($tipo = $get('tipo_combustible_id')) {
            $promedio = Vale::query()
                ->registrados()
                ->where('tipo_combustible_id', $tipo)
                ->where('fecha_carga', '>=', now()->subDays(30))
                ->avg('precio_litro');

            if ($promedio) {
                $texto .= ' (promedio últimos 30 días: $'.number_format((float) $promedio, 2).')';
            }
        }

        return $texto;
    }
}
