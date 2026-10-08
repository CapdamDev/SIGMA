<?php

namespace App\Filament\Resources\Vales;

use App\Filament\Resources\Vales\Pages\CreateVale;
use App\Filament\Resources\Vales\Pages\EditVale;
use App\Filament\Resources\Vales\Pages\ListVales;
use App\Filament\Resources\Vales\Schemas\ValeForm;
use App\Filament\Resources\Vales\Tables\ValesTable;
use App\Models\Vale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ValeResource extends Resource
{
    protected static ?string $model = Vale::class;

    protected static ?string $slug = 'vales';

    protected static ?string $modelLabel = 'vale';

    protected static ?string $pluralModelLabel = 'vales de combustible';

    protected static ?string $navigationLabel = 'Vales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return ValeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVales::route('/'),
            'create' => CreateVale::route('/create'),
            'edit' => EditVale::route('/{record}/edit'),
        ];
    }
}
