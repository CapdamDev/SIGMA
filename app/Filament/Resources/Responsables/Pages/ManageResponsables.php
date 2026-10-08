<?php

namespace App\Filament\Resources\Responsables\Pages;

use App\Filament\Resources\Responsables\ResponsableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageResponsables extends ManageRecords
{
    protected static string $resource = ResponsableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
