#!/usr/bin/env bash
# Restaura la base de datos desde un backup de deploy/backup.sh. Reemplaza TODOS los datos actuales.
#   bash deploy/restaurar.sh deploy/backups/menina_2026-11-20_0315.dump
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml --env-file deploy/.env.production"

if [ "$#" -ne 1 ] || [ ! -f "$1" ]; then
    echo "Uso: bash deploy/restaurar.sh <archivo.dump>" >&2
    echo "Backups disponibles:" >&2
    ls -1 deploy/backups/*.dump 2>/dev/null >&2 || echo "  (ninguno)" >&2
    exit 1
fi

ARCHIVO="$1"

echo "Esto reemplaza TODOS los datos actuales del sistema por los de $ARCHIVO."
read -r -p "Escribe RESTAURAR para continuar: " respuesta
if [ "$respuesta" != "RESTAURAR" ]; then
    echo "Cancelado: no se tocó nada."
    exit 1
fi

# Sin los nodos no hay conexiones abiertas a la base mientras se restaura (el sitio muestra un error
# de HAProxy durante unos segundos)
$COMPOSE stop nginx nginx_b app app_b

# --clean --if-exists borra cada tabla antes de recrearla; sirve también sobre una base vacía.
# --role: las tablas restauradas quedan a nombre del usuario de la app (APP_DB_USER). Sin esto
# quedarían del superusuario y la app no podría ni leerlas
if ! $COMPOSE exec -T db sh -c 'pg_restore --clean --if-exists --no-owner --role="$APP_DB_USER" -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "$ARCHIVO"; then
    echo "pg_restore terminó con errores. Revisa el mensaje de arriba antes de seguir." >&2
    $COMPOSE start app app_b nginx nginx_b
    exit 1
fi

$COMPOSE start app app_b nginx nginx_b
echo "Restauración terminada desde $ARCHIVO."
