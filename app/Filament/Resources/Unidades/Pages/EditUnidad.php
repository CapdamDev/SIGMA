<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadResource;
use Filament\Resources\Pages\EditRecord;

class EditUnidad extends EditRecord
{
    protected static string $resource = UnidadResource::class;

    // Muestra la pestaña de vales junto al formulario.
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
