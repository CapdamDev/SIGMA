<?php

namespace App\Filament\Resources\Usuarios;

use App\Enums\Rol;
use App\Filament\Resources\Usuarios\Pages\ManageUsuarios;
use App\Models\User;
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
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'usuarios';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Correo')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            Select::make('rol')
                ->options(Rol::class)
                ->default(Rol::Capturista)
                ->required(),
            TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Déjala vacía para no cambiarla.' : null),
            Toggle::make('activo')
                ->helperText('Un usuario inactivo no puede entrar al sistema.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('rol')->badge(),
                IconColumn::make('activo')->boolean(),
                TextColumn::make('created_at')->label('Alta')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('rol')->options(Rol::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsuarios::route('/'),
        ];
    }
}
