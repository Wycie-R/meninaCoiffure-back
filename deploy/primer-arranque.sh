#!/usr/bin/env bash
# Primer despliegue en un servidor nuevo (una sola vez). Se corre así, desde la carpeta del repo:
#   bash deploy/primer-arranque.sh
# Para las actualizaciones siguientes se usa deploy/deploy.sh.
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml --env-file deploy/.env.production"

# 1. Imágenes, base, migraciones y todo el stack arriba
bash deploy/deploy.sh "$@"

# 2. Datos reales del salón: roles, horario, métodos de pago, catálogo y promo de los miércoles.
#    No crea cuentas de prueba (DatabaseSeeder se niega a correr en producción)
$COMPOSE run --rm app php artisan db:seed --class=ProduccionSeeder --force

# 3. La cuenta de la administración. La contraseña se escribe oculta y no queda en el historial
echo
echo "Ahora se crea la cuenta de la administración (mínimo 12 caracteres de contraseña)."
$COMPOSE run --rm app php artisan salon:crear-cuenta --rol=admin

echo
echo "Listo. Siguientes pasos en deploy/README.md: cargar el personal desde el panel,"
echo "vincular la ficha de la dueña y crear las cuentas de las profesionales."
