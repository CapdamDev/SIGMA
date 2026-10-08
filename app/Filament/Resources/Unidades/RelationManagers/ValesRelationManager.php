<?php

namespace App\Filament\Resources\Unidades\RelationManagers;

use App\Filament\Resources\Vales\Tables\ValesTable;
use App\Filament\Resources\Vales\ValeResource;
use App\Models\Vale;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Historial de cargas de la unidad, con rendimiento. Solo lectura:
 * la captura y edición se hacen desde Vales.
 */
class ValesRelationManager extends RelationManager
{
    protected static string $relationship = 'vales';

    protected static ?string $title = 'Cargas de combustible';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['unidad', 'rendimiento']))
            ->columns(ValesTable::columnas(conUnidad: false))
            ->filters(ValesTable::filtros(conUnidad: false, mesActualPorDefecto: false))
            ->defaultSort('fecha_carga', 'desc')
            ->headerActions([
                ValesTable::accionExportar(),
            ])
            ->recordActions([
                Action::make('abrir')
                    ->label('Abrir')
                    ->url(fn (Vale $record) => ValeResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Vale $record) => auth()->user()?->can('update', $record)),
            ]);
    }
}
