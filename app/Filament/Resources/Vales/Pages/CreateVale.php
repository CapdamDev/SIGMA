<?php

namespace App\Filament\Resources\Vales\Pages;

use App\Filament\Resources\Vales\ValeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVale extends CreateRecord
{
    protected static string $resource = ValeResource::class;

    // Tras guardar, regresa a un formulario vacío: la captura suele ser en lote.
    protected static bool $canCreateAnother = true;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
