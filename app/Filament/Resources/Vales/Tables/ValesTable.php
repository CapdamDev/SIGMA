<?php

namespace App\Filament\Resources\Vales\Tables;

use App\Enums\EstatusVale;
use App\Exports\ValesXlsx;
use App\Models\Vale;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ValesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['unidad', 'rendimiento']))
            ->columns(static::columnas())
            ->defaultSort('fecha_carga', 'desc')
            ->filters(static::filtros(), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->headerActions([
                static::accionExportar(),
            ])
            ->recordActions([
                EditAction::make(),
                static::accionCancelar(),
            ])
            ->recordClasses(fn (Vale $record) => $record->estatus === EstatusVale::Cancelado ? 'opacity-50' : null)
            ->paginated([25, 50, 100]);
    }

    /** Columnas compartidas con la pestaña de vales de cada unidad. */
    public static function columnas(bool $conUnidad = true): array
    {
        return array_values(array_filter([
            TextColumn::make('fecha_carga')
                ->label('Fecha')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            TextColumn::make('folio')
                ->searchable()
                ->toggleable(),
            $conUnidad ? TextColumn::make('unidad.numero_economico')
                ->label('Unidad')
                ->description(fn (Vale $record) => $record->unidad?->descripcion)
                ->searchable()
                ->sortable() : null,
            TextColumn::make('direccion.nombre')
                ->label('Dirección')
                ->sortable()
                ->toggleable(),
            TextColumn::make('operador.nombre')
                ->label('Operador')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('tipoCombustible.clave')
                ->label('Comb.')
                ->badge()
                ->color(fn (?string $state) => $state === 'DIESEL' ? 'gray' : 'success'),
            TextColumn::make('lectura_medidor')
                ->label('Medidor')
                ->numeric(decimalPlaces: 0)
                ->placeholder(fn (Vale $record) => $record->medidor_valido ? '—' : 'No funciona')
                ->toggleable(),
            TextColumn::make('rendimiento.recorrido')
                ->label('Recorrido')
                ->numeric(decimalPlaces: 0)
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('rendimiento.rendimiento')
                ->label('Rend.')
                ->numeric(decimalPlaces: 2)
                ->tooltip('Recorrido desde la carga anterior entre litros cargados')
                ->color(fn (Vale $record) => static::colorRendimiento($record))
                ->placeholder(fn (Vale $record) => $record->rendimiento?->lectura_sospechosa ? 'Revisar' : '—'),
            TextColumn::make('litros')
                ->numeric(decimalPlaces: 2)
                ->alignEnd()
                ->sortable()
                ->summarize(Sum::make()->label('Litros')->numeric(decimalPlaces: 2)
                    ->query(fn ($query) => $query->where('estatus', EstatusVale::Registrado->value))),
            TextColumn::make('importe')
                ->money('MXN')
                ->alignEnd()
                ->sortable()
                ->summarize(Sum::make()->label('Importe')->money('MXN')
                    ->query(fn ($query) => $query->where('estatus', EstatusVale::Registrado->value))),
            TextColumn::make('precio_litro')
                ->label('$/L')
                ->money('MXN')
                ->alignEnd()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('estatus')
                ->badge()
                ->toggleable(),
            TextColumn::make('observaciones')
                ->limit(40)
                ->tooltip(fn (Vale $record) => $record->observaciones)
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('capturadoPor.name')
                ->label('Capturó')
                ->toggleable(isToggledHiddenByDefault: true),
        ]));
    }

    public static function filtros(bool $conUnidad = true, bool $mesActualPorDefecto = true): array
    {
        return array_values(array_filter([
            Filter::make('periodo')
                ->schema([
                    DatePicker::make('desde')->label('Desde')->default($mesActualPorDefecto ? now()->startOfMonth() : null),
                    DatePicker::make('hasta')->label('Hasta'),
                ])
                ->columns(2)
                ->columnSpan(2)
                ->query(fn (Builder $query, array $data) => $query
                    ->when($data['desde'] ?? null, fn (Builder $q, $fecha) => $q->where('fecha_carga', '>=', Carbon::parse($fecha)->startOfDay()))
                    ->when($data['hasta'] ?? null, fn (Builder $q, $fecha) => $q->where('fecha_carga', '<=', Carbon::parse($fecha)->endOfDay())))
                ->indicateUsing(function (array $data): array {
                    $indicadores = [];
                    if ($data['desde'] ?? null) {
                        $indicadores[] = 'Desde '.Carbon::parse($data['desde'])->format('d/m/Y');
                    }
                    if ($data['hasta'] ?? null) {
                        $indicadores[] = 'Hasta '.Carbon::parse($data['hasta'])->format('d/m/Y');
                    }

                    return $indicadores;
                }),
            SelectFilter::make('direccion')
                ->label('Dirección')
                ->relationship('direccion', 'nombre')
                ->multiple()
                ->preload(),
            $conUnidad ? SelectFilter::make('unidad')
                ->label('Unidad')
                ->relationship('unidad', 'numero_economico')
                ->multiple()
                ->searchable()
                ->preload() : null,
            SelectFilter::make('tipoCombustible')
                ->label('Combustible')
                ->relationship('tipoCombustible', 'nombre'),
            SelectFilter::make('operador')
                ->label('Operador')
                ->relationship('operador', 'nombre')
                ->searchable(),
            SelectFilter::make('estatus')
                ->options(EstatusVale::class)
                ->default(EstatusVale::Registrado->value),
            TernaryFilter::make('medidor_valido')
                ->label('Medidor')
                ->trueLabel('Funciona')
                ->falseLabel('No funciona'),
            Filter::make('revisar')
                ->label('Solo lecturas a revisar')
                ->toggle()
                ->query(fn (Builder $query) => $query->whereHas('rendimiento', fn (Builder $q) => $q->where('lectura_sospechosa', true))),
        ]));
    }

    public static function accionCancelar(): Action
    {
        return Action::make('cancelar')
            ->label('Cancelar')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading('Cancelar vale')
            ->modalDescription('El vale deja de contar en totales y rendimientos, pero queda en el historial.')
            ->modalSubmitActionLabel('Cancelar vale')
            ->schema([
                Textarea::make('motivo_cancelacion')
                    ->label('Motivo')
                    ->required()
                    ->maxLength(255),
            ])
            ->authorize('cancelar')
            ->action(function (Vale $record, array $data) {
                $record->update([
                    'estatus' => EstatusVale::Cancelado,
                    'motivo_cancelacion' => $data['motivo_cancelacion'],
                ]);

                Notification::make()->title('Vale cancelado')->success()->send();
            });
    }

    public static function accionExportar(): Action
    {
        return Action::make('exportar')
            ->label('Exportar a Excel')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (HasTable $livewire) => ValesXlsx::descargar($livewire->getFilteredSortedTableQuery()));
    }

    protected static function colorRendimiento(Vale $record): ?string
    {
        $rendimiento = $record->rendimiento?->rendimiento;
        $esperado = $record->unidad?->rendimiento_esperado;

        if ($rendimiento === null || ! $esperado) {
            return null;
        }

        return match (true) {
            $rendimiento > $esperado * 3, $rendimiento < $esperado * 0.6 => 'danger',
            $rendimiento < $esperado * 0.85 => 'warning',
            default => 'success',
        };
    }
}
