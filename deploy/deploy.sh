#!/usr/bin/env bash
# Despliega o actualiza Menina Coiffure en el servidor. Se corre así, desde la carpeta del repo:
#   bash deploy/deploy.sh
# Con --sin-pull no trae cambios de Git (sirve para probar el stack en tu PC).
set -euo pipefail

# Siempre desde la raíz del repositorio, aunque se llame desde otra carpeta
cd "$(dirname "$0")/.."

ARCHIVO_ENV="deploy/.env.production"
COMPOSE="docker compose -f docker-compose.prod.yml --env-file $ARCHIVO_ENV"

# Lee una variable del archivo sin ejecutarlo con 'source': las contraseñas pueden tener caracteres raros
leer_variable() {
    grep -E "^$1=" "$ARCHIVO_ENV" | tail -n 1 | cut -d '=' -f 2- | sed -e 's/^"//' -e 's/"$//'
}

# 1. Configuración completa
if [ ! -f "$ARCHIVO_ENV" ]; then
    echo "Falta $ARCHIVO_ENV. Cópialo de deploy/.env.production.example y completa los secretos." >&2
    exit 1
fi

if [ -z "$(leer_variable APP_KEY)" ]; then
    echo "APP_KEY está vacío. Genera una con:" >&2
    echo "  $COMPOSE run --rm --no-deps app php artisan key:generate --show" >&2
    echo "y pégala en $ARCHIVO_ENV (APP_KEY=base64:...)." >&2
    exit 1
fi

# Contraseñas sin completar: con el valor de ejemplo quedarían públicas (la plantilla está en GitHub)
for variable in DB_PASSWORD POSTGRES_PASSWORD HAPROXY_STATS_PASSWORD MAIL_PASSWORD; do
    valor="$(leer_variable "$variable")"
    if [ -z "$valor" ] || [ "$valor" = "cambiar-por-una-larga" ] || [ "$valor" = "clave-smtp-de-brevo" ]; then
        echo "$variable está vacía o con el valor de ejemplo en $ARCHIVO_ENV. Complétala antes de desplegar." >&2
        exit 1
    fi
done

if [ "$(leer_variable APP_DEBUG)" != "false" ]; then
    echo "APP_DEBUG tiene que ser false en producción: con true, un error muestra las contraseñas del .env." >&2
    exit 1
fi

DOMINIO="$(leer_variable DOMINIO)"

# 2. Código nuevo (solo avanza si no hay conflictos; nunca hace merge)
if [ "${1:-}" != "--sin-pull" ]; then
    git pull --ff-only
fi

# 3. Imágenes con el código, las dependencias y el build del frontend. --pull baja la última versión
#    de las imágenes base (PHP, Node, nginx) y pull la de las que se usan tal cual: sin esto, los
#    contenedores se quedarían para siempre con los fallos de seguridad del día del primer despliegue
$COMPOSE pull db haproxy caddy
$COMPOSE build --pull

# 4. La base tiene que estar sana antes de migrar
$COMPOSE up -d --wait db

# 5. Migraciones UNA sola vez, en un contenedor que se borra al terminar. Si las corriera cada nodo
#    al arrancar, A y B migrarían a la vez y podrían chocar
$COMPOSE run --rm app php artisan migrate --force

# 6. Levanta todo (o recrea los contenedores que cambiaron de imagen o de variables)
$COMPOSE up -d --remove-orphans

# 7. Comprueba que el sitio responde por HTTPS. La primera vez Caddy tarda un poco en pedir el certificado
OPCIONES_CURL="-fsS --max-time 5"
if [ "$DOMINIO" = "localhost" ]; then
    # En tu PC Caddy usa un certificado propio que curl no conoce
    OPCIONES_CURL="$OPCIONES_CURL -k"
fi

INTENTOS=30
for intento in $(seq 1 "$INTENTOS"); do
    if curl $OPCIONES_CURL "https://$DOMINIO/up" > /dev/null 2>&1; then
        echo "Listo: https://$DOMINIO responde."
        break
    fi
    if [ "$intento" -eq "$INTENTOS" ]; then
        echo "https://$DOMINIO/up no responde. Revisa los logs con:" >&2
        echo "  $COMPOSE logs --tail=100 caddy haproxy app app_b" >&2
        exit 1
    fi
    sleep 5
done

# 8. Borra las imágenes viejas que ya no usa ningún contenedor
docker image prune -f
