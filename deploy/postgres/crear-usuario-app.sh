#!/bin/sh
# La imagen de PostgreSQL ejecuta este script UNA sola vez: cuando crea la base en un volumen vacío
# (primer despliegue). Crea el usuario de la aplicación con deploy/postgres/usuario-app.sql.
# APP_DB_USER y APP_DB_PASSWORD llegan desde docker-compose.prod.yml (son DB_USERNAME y DB_PASSWORD).
# --single-transaction: si algo falla no queda nada a medias (ni el usuario creado sin la base)
psql -v ON_ERROR_STOP=1 --single-transaction \
    --username "$POSTGRES_USER" \
    --dbname "$POSTGRES_DB" \
    -v app_user="$APP_DB_USER" \
    -v app_password="$APP_DB_PASSWORD" \
    -v db_name="$POSTGRES_DB" \
    -f /menina/usuario-app.sql
