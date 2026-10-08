<?php

namespace App\Filament\Resources\Operadores;

use App\Filament\Resources\Operadores\Pages\ManageOperadores;
use App\Models\Operador;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OperadorResource extends Resource
{
    protected static ?string $model = Operador::class;

    protected static ?string $slug = 'operadores';

    protected static ?string $modelLabel = 'operador';

    protected static ?string $pluralModelLabel = 'operadores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'nombre';

    /** Campos reutilizados también por el alta rápida desde el vale. */
    public static function camposFormulario(): array
    {
        return [
            TextInput::make('nombre')
                ->required()
                ->maxLength(150),
            TextInput::make('numero_empleado')
                ->label('Número de empleado')
                ->maxLength(20)
                ->unique(ignoreRecord: true),
            Select::make('direccion_id')
                ->label('Dirección')
                ->relationship('direccion', 'nombre', fn (Builder $query) => $query->where('activo', true))
                ->searchable()
                ->preload(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...static::camposFormulario(),
            Toggle::make('activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('numero_empleado')->label('No. empleado')->searchable(),
                TextColumn::make('direccion.nombre')->label('Dirección')->sortable(),
                TextColumn::make('vales_count')->counts('vales')->label('Vales')->sortable(),
                IconColumn::make('activo')->boolean(),
            ])
            ->defaultSort('nombre')
            ->filters([
                SelectFilter::make('direccion')->label('Dirección')->relationship('direccion', 'nombre'),
                TernaryFilter::make('activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOperadores::route('/'),
        ];
    }
}
