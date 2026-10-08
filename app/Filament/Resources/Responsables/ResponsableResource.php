<?php

namespace App\Filament\Resources\Responsables;

use App\Filament\Resources\Responsables\Pages\ManageResponsables;
use App\Models\Responsable;
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

class ResponsableResource extends Resource
{
    protected static ?string $model = Responsable::class;

    protected static ?string $slug = 'responsables';

    protected static ?string $modelLabel = 'responsable';

    protected static ?string $pluralModelLabel = 'responsables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('titulo')
                ->label('Título')
                ->placeholder('ING., ARQ., LIC.')
                ->maxLength(20),
            TextInput::make('nombre')
                ->required()
                ->maxLength(150),
            Select::make('direccion_id')
                ->label('Dirección')
                ->relationship('direccion', 'nombre', fn (Builder $query) => $query->where('activo', true))
                ->searchable()
                ->preload(),
            Toggle::make('activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')->label('Título'),
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('direccion.nombre')->label('Dirección')->sortable(),
                TextColumn::make('unidades_count')->counts('unidades')->label('Unidades a cargo'),
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
            'index' => ManageResponsables::route('/'),
        ];
    }
}
