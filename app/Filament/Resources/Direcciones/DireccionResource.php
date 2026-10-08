<?php

namespace App\Filament\Resources\Direcciones;

use App\Filament\Resources\Direcciones\Pages\ManageDirecciones;
use App\Models\Direccion;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class DireccionResource extends Resource
{
    protected static ?string $model = Direccion::class;

    protected static ?string $slug = 'direcciones';

    protected static ?string $modelLabel = 'dirección';

    protected static ?string $pluralModelLabel = 'direcciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),
            Toggle::make('activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('unidades_count')->counts('unidades')->label('Unidades')->sortable(),
                IconColumn::make('activo')->boolean(),
            ])
            ->defaultSort('nombre')
            ->filters([
                TernaryFilter::make('activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDirecciones::route('/'),
        ];
    }
}
