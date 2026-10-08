<?php

namespace App\Filament\Resources\Unidades\Schemas;

use App\Enums\ClaseUnidad;
use App\Enums\EstatusUnidad;
use App\Enums\TipoMedidor;
use App\Models\Responsable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UnidadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Identificación')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('numero_economico')
                            ->label('Número económico')
                            ->placeholder('ECO 126')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state) => preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $state)))),
                        Select::make('clase')
                            ->options(ClaseUnidad::class)
                            ->default(ClaseUnidad::Vehiculo)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $clase = $state instanceof ClaseUnidad ? $state : ClaseUnidad::tryFrom((string) $state);
                                if ($clase) {
                                    $set('tipo_medidor', $clase->medidorPorDefecto());
                                }
                            }),
                        TextInput::make('descripcion')
                            ->label('Descripción')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('marca')
                            ->maxLength(50),
                        TextInput::make('anio_modelo')
                            ->label('Año modelo')
                            ->numeric()
                            ->minValue(1950)
                            ->maxValue(now()->year + 1),
                        TextInput::make('placa')
                            ->maxLength(15)
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? mb_strtoupper(trim($state)) : null),
                        Select::make('estatus')
                            ->options(EstatusUnidad::class)
                            ->default(EstatusUnidad::Activa)
                            ->required(),
                    ]),

                Section::make('Asignación actual')
                    ->description('Los vales guardan la asignación del momento de la carga, así que cambiarla aquí no altera el historial.')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('direccion_id')
                            ->label('Dirección')
                            ->relationship('direccion', 'nombre', fn (Builder $query) => $query->where('activo', true))
                            ->searchable()
                            ->preload(),
                        Select::make('responsable_id')
                            ->label('Responsable')
                            ->relationship('responsable', 'nombre', fn (Builder $query) => $query->where('activo', true))
                            ->getOptionLabelFromRecordUsing(fn (Responsable $record) => $record->nombre_completo)
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Combustible y medidor')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('tipo_combustible_id')
                            ->label('Combustible')
                            ->relationship('tipoCombustible', 'nombre'),
                        TextInput::make('capacidad_tanque_l')
                            ->label('Capacidad del tanque')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(2000)
                            ->suffix('L'),
                        Select::make('tipo_medidor')
                            ->label('Medidor')
                            ->options(TipoMedidor::class)
                            ->default(TipoMedidor::Km)
                            ->required()
                            ->live(),
                        TextInput::make('rendimiento_esperado')
                            ->label('Rendimiento esperado')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix(fn (Get $get) => $get('tipo_medidor') === TipoMedidor::Horas->value || $get('tipo_medidor') === TipoMedidor::Horas ? 'h/L' : 'km/L')
                            ->helperText('Se usa para marcar cargas con rendimiento bajo o lecturas dudosas.')
                            ->hidden(fn (Get $get) => in_array($get('tipo_medidor'), [TipoMedidor::Ninguno, TipoMedidor::Ninguno->value], true)),
                    ]),

                Textarea::make('notas')
                    ->rows(2)
                    ->columnSpan(1),
            ]);
    }
}
