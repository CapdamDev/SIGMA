#!/bin/sh
set -e

cd /var/www/html

# Solo el proceso principal (php-fpm) prepara la app; los "exec" y "run" de
# comandos artisan sueltos no deben migrar ni recachear.
if [ "$1" = "php-fpm" ]; then
    if [ -z "$APP_KEY" ]; then
        echo "ERROR: falta APP_KEY en .env."
        echo "Genera una con: echo \"base64:\$(openssl rand -base64 32)\""
        exit 1
    fi

    # El volumen de storage puede venir vacío o de una versión anterior
    mkdir -p storage/app/importacion storage/framework/cache/data \
             storage/framework/sessions storage/framework/views storage/logs

    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        tries=0
        until php artisan migrate --force; do
            tries=$((tries + 1))
            if [ "$tries" -ge 10 ]; then
                echo "ERROR: no se pudo migrar la base de datos."
                exit 1
            fi
            echo "Base de datos no disponible, reintentando en 3 s…"
            sleep 3
        done
        php artisan db:seed --force
    fi

    php artisan optimize
    chown -R www-data:www-data storage bootstrap/cache
fi

exec "$@"
