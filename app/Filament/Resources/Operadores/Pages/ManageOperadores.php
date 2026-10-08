<?php

namespace App\Filament\Resources\Operadores\Pages;

use App\Filament\Resources\Operadores\OperadorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOperadores extends ManageRecords
{
    protected static string $resource = OperadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
