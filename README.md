# Control de Combustible

Panel para registrar y controlar vales de combustible de la flotilla: unidades, cargas, rendimiento, gasto por dirección y exportación a Excel. Reemplaza la hoja de Google Sheets + Apps Script.

Laravel 13 · Filament 5 · MySQL 8+ o MariaDB 10.6+ · PHP 8.3+

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edita en `.env` la conexión a la base de datos (`DB_*`) y `APP_URL`. Luego:

```bash
php artisan migrate --seed          # tablas, vista de rendimiento y tipos de combustible
php artisan make:filament-user      # el PRIMER usuario queda como administrador
php artisan serve                   # o apunta tu servidor web a /public
```

El panel vive en la raíz del sitio (`/`). Para producción: `APP_ENV=production`, `APP_DEBUG=false` y `php artisan optimize`.

## Despliegue con Docker

Tres contenedores: `app` (PHP-FPM 8.4 con el código), `web` (Nginx, único puerto expuesto) y `db` (MariaDB 11.4). Los datos viven en dos volúmenes: `dbdata` (base de datos) y `storage` (logs, sesiones de archivo, reportes de importación).

```bash
cp .env.docker.example .env
# Edita APP_URL, APP_PORT y las contraseñas de base de datos
docker compose build
echo "base64:$(openssl rand -base64 32)"                                # pega el valor en APP_KEY
docker compose up -d
docker compose exec app php artisan make:filament-user                  # primer usuario = administrador
```

Al arrancar, `app` espera a la base de datos, corre migraciones y el seeder (idempotente) y cachea configuración, rutas y vistas. Para desactivar las migraciones automáticas pon `RUN_MIGRATIONS=false`.

El panel queda en `http://servidor:${APP_PORT}`. Si lo publicas con Cloudflare Tunnel o detrás de otro proxy inverso, apunta el proxy a ese puerto; la app ya confía en los encabezados `X-Forwarded-*`, así que genera URLs `https` correctas. Deja `SESSION_SECURE_COOKIE=true` solo si el acceso es por https.

### Importar el histórico dentro del contenedor

```bash
docker compose cp Control_Flotillas.xlsx app:/tmp/
docker compose exec app php artisan combustible:importar /tmp/Control_Flotillas.xlsx --dry-run
docker compose exec app ls storage/app/importacion                       # reportes
docker compose cp app:/var/www/html/storage/app/importacion ./reportes   # copiarlos al host
docker compose exec app php artisan combustible:importar /tmp/Control_Flotillas.xlsx --fresh
```

### Actualizar

```bash
git pull   # o copia el código nuevo
docker compose build && docker compose up -d
```

Genera y versiona `composer.lock` (corre `composer install` una vez fuera de Docker y súbelo) para que cada build instale exactamente las mismas versiones.

### Respaldos

```bash
docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines "$MARIADB_DATABASE"' \
  | gzip > respaldo_combustible_$(date +%F).sql.gz
```

Programa ese comando en el cron del host (por ejemplo, diario a las 2:00) y guarda las copias fuera del servidor. Para restaurar:

```bash
gunzip -c respaldo.sql.gz | docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
```

## Importar el histórico del Google Sheets

Descarga la hoja como `.xlsx` (Archivo → Descargar → Microsoft Excel). Primero corre una prueba que no guarda nada:

```bash
php artisan combustible:importar /ruta/Control_Flotillas.xlsx --dry-run
```

Revisa el reporte CSV que deja en `storage/app/importacion/`. Ahí aparece todo lo que no pudo mapear o que conviene verificar: vales de unidades que no existen en la hoja `unidades`, litros o importes absurdos, precios atípicos, capacidades de tanque inválidas, etc. Corrige en la hoja lo que valga la pena y vuelve a probar. Cuando esté listo:

```bash
php artisan combustible:importar /ruta/Control_Flotillas.xlsx --fresh
```

`--fresh` vacía vales, unidades y catálogos antes de importar. Úsalo solo antes de salir a producción.

Qué limpia el importador:

- `folio_vale`: 0 → sin folio; número → folio; texto → operador (se crea en el catálogo).
- `id_unidad`: acepta id numérico o número económico (`BID 014`, `G003` → `G 003`). Si el id no existe en la hoja, intenta `ECO nnn` y lo marca en el reporte. IDs repetidos en la hoja se omiten.
- `km_carga` en 0, "km no funciona" o "N/F" → medidor no válido (no cuenta para rendimiento). Textos como `177626(RENTADA)` conservan el número y el texto original va a observaciones.
- `usuarios_responsables`: separa título (ING., ARQ., LIC.) y omite lo que no son personas. **Revisa las constantes al inicio de `app/Console/Commands/ImportarHistorico.php`** (`RESPONSABLES_NO_PERSONAS`, `RESPONSABLES_FUSIONAR`) contra tu hoja actual.
- Unidades: deduce la clase (vehículo, moto, maquinaria, pipa, bidón, generador, concepto) y el tipo de medidor; "fuera de operación" y "trámite de baja" pasan a estatus.

## Roles

| Rol | Puede |
|---|---|
| Administrador | Todo: catálogos, unidades, usuarios, cancelar vales |
| Capturista | Registrar y corregir vales, dar de alta operadores |
| Consulta | Ver vales, unidades, dashboard y exportar |

Nada se borra: los vales se cancelan con motivo y los catálogos se desactivan.

## Cómo se calcula el rendimiento

La vista `v_vales_rendimiento` compara cada carga con la carga válida anterior de la misma unidad, ordenadas por fecha de carga: `(lectura − lectura anterior) / litros de esta carga`. Las cargas con medidor descompuesto o canceladas no participan. Si la lectura retrocede, el vale se marca como "lectura a revisar" en lugar de dar un número negativo.

Es un rendimiento aproximado: es exacto solo si cada carga llena el tanque.

Al capturar, el formulario rechaza lecturas que no avanzan, que superan a una carga posterior o que darían más del triple del rendimiento esperado de la unidad (el típico dígito de más). Si la lectura es correcta de todos modos (cambio de odómetro), el capturista la confirma y explica en observaciones.

## Pruebas

```bash
php artisan test
```

Usan una base MySQL/MariaDB llamada `control_combustible_test` (ver `phpunit.xml`); créala antes. No se usa SQLite porque la vista y los reportes usan funciones de MySQL.

## Estructura

- `app/Models` — `Vale`, `Unidad`, `ValeRendimiento` (vista), catálogos.
- `app/Filament/Resources/Vales` — formulario de captura con validación de lecturas, tabla con filtros, totales, exportación y cancelación.
- `app/Filament/Resources/Unidades` — unidades con su historial de cargas.
- `app/Filament/Widgets` — resumen del mes, gasto por dirección, consumo de 12 meses.
- `app/Exports/ValesXlsx.php` — exportación síncrona a Excel de lo filtrado.
- `app/Console/Commands/ImportarHistorico.php` — importador del XLSX.
