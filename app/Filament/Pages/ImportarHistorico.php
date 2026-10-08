<?php

namespace App\Filament\Pages;

use App\Services\ImportadorHistoricoService;
use App\Services\ImportSnapshotService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Importa el histórico de vales desde Excel y permite revertir una importación
 * que haya salido mal, restaurando el respaldo tomado justo antes de importar.
 *
 * La lógica real vive en App\Services\ImportadorHistoricoService (import) y
 * App\Services\ImportSnapshotService (respaldo/revertir) — también usada por
 * `php artisan combustible:importar`.
 */
class ImportarHistorico extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Importar histórico';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected string $view = 'filament.pages.importar-historico';

    public ?array $resultadoPreview = null;

    public ?array $resultadoImportacion = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->esAdmin() ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Importa el histórico de vales desde el Excel exportado de Google Sheets (hojas: direcciones, usuarios_responsables, unidades, vales_gasolina). Usa "Vista previa" para revisar sin guardar nada, y "Importar" cuando los números se vean bien.'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->vistaPreviaAction(),
            $this->importarAction(),
        ];
    }

    private function campoArchivo(): FileUpload
    {
        return FileUpload::make('archivo')
            ->label('Archivo Excel (.xlsx)')
            ->disk('local')
            ->directory('importacion/subidas')
            ->visibility('private')
            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->maxSize(20 * 1024)
            ->required();
    }

    private function vistaPreviaAction(): Action
    {
        return Action::make('vistaPrevia')
            ->label('Vista previa')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading('Vista previa de importación')
            ->modalDescription('Procesa el archivo y muestra los resultados, pero no guarda nada en la base de datos.')
            ->modalSubmitActionLabel('Procesar (sin guardar)')
            ->schema([$this->campoArchivo()])
            ->action(function (array $data) {
                $ruta = Storage::disk('local')->path($data['archivo']);

                try {
                    $this->resultadoImportacion = null;
                    $this->resultadoPreview = app(ImportadorHistoricoService::class)
                        ->ejecutar($ruta, dryRun: true);

                    Notification::make()
                        ->title('Vista previa lista')
                        ->body('No se guardó nada. Revisa los números abajo.')
                        ->success()
                        ->send();
                } catch (RuntimeException $e) {
                    Notification::make()
                        ->title('No se pudo procesar el archivo')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                } finally {
                    Storage::disk('local')->delete($data['archivo']);
                }
            });
    }

    private function importarAction(): Action
    {
        return Action::make('importar')
            ->label('Importar')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('danger')
            ->modalHeading('Importar histórico')
            ->modalDescription('Antes de escribir nada se guarda un respaldo de los datos actuales, por si hay que revertir.')
            ->modalSubmitActionLabel('Importar')
            ->schema([
                $this->campoArchivo(),
                Toggle::make('reemplazar')
                    ->label('Reemplazar todo')
                    ->helperText('Borra direcciones, responsables, operadores, unidades y vales existentes antes de importar. Úsalo solo si ya hay datos de prueba que no sirven.')
                    ->default(false),
            ])
            ->action(function (array $data) {
                $ruta = Storage::disk('local')->path($data['archivo']);
                $respaldo = app(ImportSnapshotService::class)->crear(
                    motivo: 'Antes de importar '.($data['archivo'] ? basename($data['archivo']) : '')
                );

                try {
                    $this->resultadoPreview = null;
                    $this->resultadoImportacion = app(ImportadorHistoricoService::class)
                        ->ejecutar($ruta, dryRun: false, fresh: (bool) ($data['reemplazar'] ?? false));

                    Notification::make()
                        ->title('Importación completa')
                        ->body('Se guardó un respaldo antes de importar — si algo no cuadra, puedes revertir desde la lista de abajo.')
                        ->success()
                        ->send();
                } catch (RuntimeException $e) {
                    Notification::make()
                        ->title('La importación falló y no se guardó nada')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    // La importación en sí ya se revirtió sola (transacción). El respaldo
                    // que se acaba de tomar no sirvió para nada: se descarta.
                    app(ImportSnapshotService::class)->eliminar($respaldo);
                } finally {
                    Storage::disk('local')->delete($data['archivo']);
                }
            });
    }

    /**
     * @return array<int, array{archivo: string, creado_en: string, motivo: string, tamano: int}>
     */
    public function historial(): array
    {
        return app(ImportSnapshotService::class)->listar();
    }

    public function revertir(string $archivo): void
    {
        abort_unless(auth()->user()?->esAdmin(), 403);

        try {
            app(ImportSnapshotService::class)->restaurar($archivo);

            Notification::make()
                ->title('Datos restaurados')
                ->body('La base de datos volvió al estado de ese respaldo.')
                ->success()
                ->send();
        } catch (RuntimeException $e) {
            Notification::make()
                ->title('No se pudo revertir')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function eliminarRespaldo(string $archivo): void
    {
        abort_unless(auth()->user()?->esAdmin(), 403);

        app(ImportSnapshotService::class)->eliminar($archivo);

        Notification::make()->title('Respaldo eliminado')->success()->send();
    }
}
