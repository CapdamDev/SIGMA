<?php

namespace App\Filament\Resources\Vales\Pages;

use App\Filament\Resources\Vales\Tables\ValesTable;
use App\Filament\Resources\Vales\ValeResource;
use Filament\Resources\Pages\EditRecord;

class EditVale extends EditRecord
{
    protected static string $resource = ValeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ValesTable::accionCancelar()
                ->successRedirectUrl(fn () => $this->getResource()::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
