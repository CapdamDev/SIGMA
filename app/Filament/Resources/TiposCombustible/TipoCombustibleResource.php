<?php

namespace App\Filament\Resources\TiposCombustible;

use App\Filament\Resources\TiposCombustible\Pages\ManageTiposCombustible;
use App\Models\TipoCombustible;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TipoCombustibleResource extends Resource
{
    protected static ?string $model = TipoCombustible::class;

    protected static ?string $slug = 'tipos-combustible';

    protected static ?string $modelLabel = 'tipo de combustible';

    protected static ?string $pluralModelLabel = 'tipos de combustible';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('clave')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state) => mb_strtoupper(trim((string) $state))),
            TextInput::make('nombre')
                ->required()
                ->maxLength(50),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clave')->badge(),
                TextColumn::make('nombre'),
                TextColumn::make('vales_count')->counts('vales')->label('Vales'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTiposCombustible::route('/'),
        ];
    }
}
