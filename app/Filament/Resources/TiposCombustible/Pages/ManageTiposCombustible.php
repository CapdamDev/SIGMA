<?php

namespace App\Filament\Resources\TiposCombustible\Pages;

use App\Filament\Resources\TiposCombustible\TipoCombustibleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTiposCombustible extends ManageRecords
{
    protected static string $resource = TipoCombustibleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
