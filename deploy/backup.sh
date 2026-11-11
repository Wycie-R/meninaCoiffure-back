#!/usr/bin/env bash
# Copia de la base de datos. Guarda un .dump en deploy/backups/, borra los de más de 14 días y,
# si hay un remoto de rclone llamado 'respaldo', sube una copia fuera del servidor.
#
# Para que corra todos los días a las 03:15, agregar con 'crontab -e' (usuario deploy):
#   15 3 * * * bash /opt/menina/deploy/backup.sh >> /opt/menina/deploy/backups/backup.log 2>&1
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml --env-file deploy/.env.production"
CARPETA="deploy/backups"
DIAS_A_GUARDAR=14

mkdir -p "$CARPETA"
ARCHIVO="$CARPETA/menina_$(date +%F_%H%M).dump"

# pg_dump corre dentro del contenedor, que ya tiene POSTGRES_USER y POSTGRES_DB: así no hace falta
# leer las contraseñas acá. Primero a un .tmp: si falla a mitad, no queda un backup roto con nombre válido
if ! $COMPOSE exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$ARCHIVO.tmp"; then
    rm -f "$ARCHIVO.tmp"
    echo "$(date '+%F %T') ERROR: pg_dump falló, no se guardó ningún backup." >&2
    exit 1
fi
mv "$ARCHIVO.tmp" "$ARCHIVO"
echo "$(date '+%F %T') Backup guardado: $ARCHIVO ($(du -h "$ARCHIVO" | cut -f1))"

# Retención: los backups locales de más de 14 días se borran
find "$CARPETA" -name 'menina_*.dump' -mtime +"$DIAS_A_GUARDAR" -delete

# Copia fuera del servidor: si el VPS se pierde, los backups locales se pierden con él
if command -v rclone > /dev/null 2>&1 && rclone listremotes | grep -q '^respaldo:$'; then
    rclone copy "$ARCHIVO" respaldo:menina-backups/
    echo "$(date '+%F %T') Copia subida a respaldo:menina-backups/"
else
    echo "$(date '+%F %T') AVISO: no hay un remoto de rclone 'respaldo'. El backup quedó solo en este servidor." >&2
fi
