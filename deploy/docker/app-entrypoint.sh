#!/bin/sh
# Arranque del contenedor de la app (nodo A, nodo B y los "run --rm" de migraciones y comandos).
set -e

# Cachea config, rutas, vistas y eventos con las variables de ESTE contenedor. Por eso, después de
# cambiar deploy/.env.production hay que recrear los contenedores (deploy.sh lo hace)
php artisan optimize

# optimize corre como root: los workers de PHP-FPM (www-data) tienen que poder escribir ahí
chown -R www-data:www-data storage bootstrap/cache

# Ejecuta lo que pida Compose: php-fpm por defecto, o 'php artisan migrate' en un 'run --rm'
exec "$@"
