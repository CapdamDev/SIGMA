<?php

namespace App\Filament\Resources\Unidades;

use App\Filament\Resources\Unidades\Pages\CreateUnidad;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Filament\Resources\Unidades\Pages\ListUnidades;
use App\Filament\Resources\Unidades\RelationManagers\ValesRelationManager;
use App\Filament\Resources\Unidades\Schemas\UnidadForm;
use App\Filament\Resources\Unidades\Tables\UnidadesTable;
use App\Models\Unidad;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class UnidadResource extends Resource
{
    protected static ?string $model = Unidad::class;

    protected static ?string $slug = 'unidades';

    protected static ?string $modelLabel = 'unidad';

    protected static ?string $pluralModelLabel = 'unidades';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'numero_economico';

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero_economico', 'placa', 'descripcion'];
    }

    public static function form(Schema $schema): Schema
    {
        return UnidadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnidadesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ValesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnidades::route('/'),
            'create' => CreateUnidad::route('/create'),
            'edit' => EditUnidad::route('/{record}/edit'),
        ];
    }
}
