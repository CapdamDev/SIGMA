<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnidades extends ListRecords
{
    protected static string $resource = UnidadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
