<?php

namespace App\Filament\Resources\Vales\Pages;

use App\Filament\Resources\Vales\ValeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVales extends ListRecords
{
    protected static string $resource = ValeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Registrar vale'),
        ];
    }
}
