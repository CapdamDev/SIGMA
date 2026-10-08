<?php

namespace App\Filament\Resources\Unidades\Tables;

use App\Enums\ClaseUnidad;
use App\Enums\EstatusUnidad;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UnidadesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_economico')
                    ->label('No. económico')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->description(fn ($record) => trim(($record->marca ?? '').' '.($record->anio_modelo ?? '')))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('clase')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('placa')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('tipoCombustible.clave')
                    ->label('Comb.')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'DIESEL' ? 'gray' : 'success'),
                TextColumn::make('direccion.nombre')
                    ->label('Dirección')
                    ->sortable(),
                TextColumn::make('responsable.nombre')
                    ->label('Responsable')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ultimaCargaConLectura.lectura_medidor')
                    ->label('Última lectura')
                    ->numeric(decimalPlaces: 0)
                    ->toggleable(),
                TextColumn::make('ultimaCargaConLectura.fecha_carga')
                    ->label('Última carga')
                    ->date('d/m/Y')
                    ->toggleable(),
                TextColumn::make('estatus')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('numero_economico')
            ->filters([
                SelectFilter::make('estatus')
                    ->options(EstatusUnidad::class)
                    ->multiple()
                    ->default([EstatusUnidad::Activa->value]),
                SelectFilter::make('clase')
                    ->options(ClaseUnidad::class)
                    ->multiple(),
                SelectFilter::make('direccion')
                    ->label('Dirección')
                    ->relationship('direccion', 'nombre')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('tipoCombustible')
                    ->label('Combustible')
                    ->relationship('tipoCombustible', 'nombre'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
