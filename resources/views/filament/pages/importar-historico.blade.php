<x-filament-panels::page>
    {{ $this->content }}

    @if ($resultadoPreview)
        <x-filament::section
            heading="Vista previa (no se guardó nada)"
            icon="heroicon-o-eye"
            icon-color="gray"
        >
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <x-filament::badge color="gray">Direcciones: {{ $resultadoPreview['direcciones'] }}</x-filament::badge>
                <x-filament::badge color="gray">Responsables: {{ $resultadoPreview['responsables'] }}</x-filament::badge>
                <x-filament::badge color="gray">Unidades: {{ $resultadoPreview['unidades'] }}</x-filament::badge>
                <x-filament::badge color="gray">Operadores: {{ $resultadoPreview['operadores'] }}</x-filament::badge>
                <x-filament::badge color="success">Vales importados: {{ $resultadoPreview['importados'] }}</x-filament::badge>
                <x-filament::badge color="danger">Vales omitidos: {{ $resultadoPreview['omitidos'] }}</x-filament::badge>
                <x-filament::badge color="warning">Avisos: {{ $resultadoPreview['avisos'] }}</x-filament::badge>
            </div>

            @if ($resultadoPreview['reporte_csv'])
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    Reporte completo de avisos: <code>{{ $resultadoPreview['reporte_csv'] }}</code>
                </p>
            @endif
        </x-filament::section>
    @endif

    @if ($resultadoImportacion)
        <x-filament::section
            heading="Última importación"
            icon="heroicon-o-check-circle"
            icon-color="success"
        >
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <x-filament::badge color="gray">Direcciones: {{ $resultadoImportacion['direcciones'] }}</x-filament::badge>
                <x-filament::badge color="gray">Responsables: {{ $resultadoImportacion['responsables'] }}</x-filament::badge>
                <x-filament::badge color="gray">Unidades: {{ $resultadoImportacion['unidades'] }}</x-filament::badge>
                <x-filament::badge color="gray">Operadores: {{ $resultadoImportacion['operadores'] }}</x-filament::badge>
                <x-filament::badge color="success">Vales importados: {{ $resultadoImportacion['importados'] }}</x-filament::badge>
                <x-filament::badge color="danger">Vales omitidos: {{ $resultadoImportacion['omitidos'] }}</x-filament::badge>
                <x-filament::badge color="warning">Avisos: {{ $resultadoImportacion['avisos'] }}</x-filament::badge>
            </div>

            @if ($resultadoImportacion['reporte_csv'])
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    Reporte completo de avisos: <code>{{ $resultadoImportacion['reporte_csv'] }}</code>
                </p>
            @endif
        </x-filament::section>
    @endif

    <x-filament::section
        heading="Respaldos e importaciones anteriores"
        description="Cada vez que importas se guarda un respaldo de los datos previos. Revertir restaura la base de datos exactamente a ese momento — se pierde cualquier cambio hecho después."
        icon="heroicon-o-clock"
    >
        @php($historial = $this->historial())

        @if (empty($historial))
            <p class="text-sm text-gray-500 dark:text-gray-400">Todavía no hay respaldos.</p>
        @else
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($historial as $snapshot)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-950 dark:text-white">
                                {{ \Illuminate\Support\Carbon::parse($snapshot['creado_en'])->translatedFormat('d/M/Y H:i:s') }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $snapshot['motivo'] ?: 'Sin descripción' }} &middot; {{ number_format($snapshot['tamano'] / 1024, 1) }} KB
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-filament::button
                                color="warning"
                                size="sm"
                                wire:click="revertir('{{ $snapshot['archivo'] }}')"
                                wire:confirm="¿Revertir la base de datos a este respaldo? Se perderá cualquier cambio hecho después de {{ \Illuminate\Support\Carbon::parse($snapshot['creado_en'])->translatedFormat('d/M/Y H:i:s') }}."
                            >
                                Revertir
                            </x-filament::button>

                            <x-filament::icon-button
                                icon="heroicon-o-trash"
                                color="danger"
                                label="Eliminar respaldo"
                                wire:click="eliminarRespaldo('{{ $snapshot['archivo'] }}')"
                                wire:confirm="¿Eliminar este respaldo? No podrás revertir a este punto después."
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
