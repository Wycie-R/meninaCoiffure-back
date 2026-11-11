# Menina Coiffure

Sistema web de gestión para Menina Coiffure, salón de estética y peluquería de Villa Elisa: agenda de reservas en el salón y a domicilio, clientas, servicios, personal, pagos, promociones, reportes y cuentas para que las clientas pidan citas. La infraestructura reparte el tráfico entre dos nodos con HAProxy; las cuentas, el registro y la recuperación de contraseña los maneja Laravel, y los avisos llegan por correo. API en Laravel con PostgreSQL y frontend con Vue 3 e Inertia; todo corre con Docker. El código de Laravel está en `src/`.



## Instalación

Requisitos: [Docker](https://docs.docker.com/engine/install/) con Docker Compose y [Git](https://git-scm.com/).

1. Clonar el repositorio:
   ```bash
   git clone https://github.com/Wycie-R/meninaCoiffure-back.git
   cd meninaCoiffure-back
   ```
2. Crear el archivo de entorno y dar permisos de escritura:
   ```bash
   cp src/.env.example src/.env
   sudo chmod 666 src/.env
   sudo chmod -R 777 src/storage src/bootstrap/cache
   ```
3. Levantar los contenedores:
   ```bash
   docker compose up -d --build
   ```
4. Instalar dependencias, generar la clave y crear la base con datos de prueba:
   ```bash
   docker compose exec app composer install
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   ```
5. Compilar el frontend:
   ```bash
   docker compose exec app npm install
   docker compose exec app npm run build
   ```

El sistema queda en [http://localhost:8000](http://localhost:8000) y la documentación de la API en [http://localhost:8000/docs](http://localhost:8000/docs) (con la sesión de la administración iniciada).

Usuarios de prueba (contraseña `password123`): `admin@meninacoiffure.com` (administración), `carla@meninacoiffure.com` (profesional) y `clienta@meninacoiffure.com` (clienta con cuenta: ve sus citas y pide nuevas en /mi-cuenta).

Los datos de prueba usan el catálogo real del salón (lista de precios 2025, en `ProduccionSeeder`) con un equipo, clientas y reservas de ejemplo. Los tests automáticos usan su propio catálogo fijo (`SprintUnoDosSeeder` y `AvanceDosSeeder`).



## Producción

El sistema se publica en un VPS (Contabo Cloud VPS 4) con Docker Compose: Caddy pone el HTTPS con Let's Encrypt, HAProxy reparte entre los dos nodos y PostgreSQL queda sin puertos abiertos. El código, las dependencias y el frontend compilado van dentro de las imágenes (`Dockerfile.prod`).

Todo el procedimiento (preparar el servidor, DNS, primer despliegue, cuentas del equipo, actualizaciones, backups y restauración) está en **[deploy/README.md](deploy/README.md)**.



## Actualización

Después de traer cambios del repositorio:

```bash
git fetch
git pull
docker compose up -d --build --remove-orphans
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app php artisan config:clear
docker compose exec app npm install
docker compose exec app npm run build
```

`--remove-orphans` borra los contenedores de servicios que ya no están en `docker-compose.yml` (por ejemplo, el `menina_keycloak` de versiones anteriores).



## Cuentas e inicio de sesión

Las cuentas viven en la tabla `users` de la base del salón. Laravel guarda las contraseñas hasheadas y las sesiones en PostgreSQL, así que cualquier nodo atiende a cualquier usuaria.

- **Iniciar sesión:** [http://localhost:8000/login](http://localhost:8000/login). "Seguir conectado" mantiene la sesión aunque se cierre el navegador. Después de 5 intentos fallidos con el mismo correo hay que esperar un minuto.
- **Crear cuenta (clientas):** [http://localhost:8000/registro](http://localhost:8000/registro). La cuenta queda con su ficha de clienta y entra directo a `/mi-cuenta`.
- **¿Olvidaste tu contraseña?:** [http://localhost:8000/recuperar-contrasena](http://localhost:8000/recuperar-contrasena). Llega un correo con un enlace de un solo uso que vence en 60 minutos. Necesita el correo configurado (ver abajo).
- **Cuentas del equipo** (administración y profesionales): no se crean desde ninguna pantalla, solo por terminal:
  ```bash
  docker compose exec app php artisan salon:crear-cuenta --rol=admin
  docker compose exec app php artisan salon:crear-cuenta --rol=profesional --personal-email=correo-de-su-ficha@gmail.com
  docker compose exec app php artisan salon:vincular-ficha correo-de-la-cuenta correo-de-la-ficha
  ```



## Balanceo de carga con HAProxy

Todo el tráfico entra por HAProxy (puerto 8000), que lo reparte entre dos nodos con la misma aplicación. Cada nodo tiene su propio PHP-FPM; las sesiones y la caché viven en PostgreSQL, así que cualquier nodo atiende a cualquier usuaria.

| Nodo | Servidor web | PHP-FPM | Nombre en HAProxy |
|---|---|---|---|
| A | `nginx` (`menina_nginx`) | `app` (`menina_app`) | `nodo_a` |
| B | `nginx_b` (`menina_nginx_b`) | `app_b` (`menina_app_b`) | `nodo_b` |

- **Algoritmo:** `leastconn` (cada petición va al nodo con menos trabajo en curso).
- **Health check:** `GET /up` cada 1 s; un nodo que falla 2 veces sale del reparto y vuelve tras 2 respuestas correctas.
- **Panel de estadísticas:** [http://localhost:8404/stats](http://localhost:8404/stats) (usuario `admin`, contraseña `admin`). Desde ahí se puede poner un nodo en mantenimiento.
- **Página de demostración en vivo:** [http://localhost:8000/balanceo](http://localhost:8000/balanceo). Genera tráfico y muestra qué nodo atendió cada petición. Funciona en modo demostración (`APP_DEMO`): en desarrollo está activo salvo que `src/.env` diga `APP_DEMO=false`; en producción responde 404.
- **Ver qué nodo respondió una petición:** cabecera `X-Servido-Por` en la respuesta, o `docker compose logs -f haproxy`.
- **Probar el failover:** con el tráfico continuo encendido en `/balanceo`, ejecutar `docker compose stop nginx_b` (todo pasa al nodo A) y luego `docker compose start nginx_b` (vuelve a repartir).
- **Validar la configuración** después de editar `haproxy.cfg`: `docker compose run --rm haproxy haproxy -c -f /usr/local/etc/haproxy/haproxy.cfg` y luego `docker compose restart haproxy`.



## Correo (avisos y recuperación de contraseña)

Cuando una clienta pide una cita, la dueña recibe un correo con la clienta, los servicios, el día, la hora y, si es a domicilio, el enlace para llegar. El mismo correo se usa para mandar los enlaces de "¿Olvidaste tu contraseña?". Sin configurar, el sistema funciona igual y los correos solo se escriben en `src/storage/logs/laravel.log`.

1. En la cuenta de Gmail que envía: activar la **Verificación en 2 pasos** y crear una **contraseña de aplicación** (Cuenta de Google > Seguridad > Contraseñas de aplicaciones).
2. Completar en `src/.env`:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=cuenta@gmail.com
   MAIL_PASSWORD=la-contraseña-de-aplicación
   MAIL_FROM_ADDRESS=cuenta@gmail.com
   MAIL_FROM_NAME="Menina Coiffure"
   AVISOS_CORREO_DESTINO=correo-de-la-dueña@gmail.com
   ```
3. Probar el envío:
   ```bash
   docker compose exec app php artisan config:clear
   docker compose exec app php artisan correo:probar
   ```
   También se puede probar con otro destino: `php artisan correo:probar alguien@gmail.com`.



## Comandos frecuentes

- **Compilar el frontend:** `docker compose exec app npm run build`
- **Aplicar migraciones:** `docker compose exec app php artisan migrate`
- **Reiniciar la base con datos de prueba** (borra todo; carga el catálogo real del salón): `docker compose exec app php artisan migrate:fresh --seed`
- **Cargar datos de volumen para el benchmark:** `docker compose exec app php artisan db:seed --class=VolumenReservasSeeder`
- **Cargar solo la clienta de demo y sus pedidos** (en una base ya cargada): `docker compose exec app php artisan db:seed --class=ClientaDemoSeeder`
- **Ejecutar las pruebas:** `docker compose exec app php artisan test`
- **Limpiar la caché de rutas:** `docker compose exec app php artisan route:clear`
- **Ver logs:** `docker compose logs -f app` (nodo B: `docker compose logs -f app_b`; balanceador: `docker compose logs -f haproxy`)
- **Entrar al contenedor:** `docker compose exec app bash`
- **Detener los servicios:** `docker compose down`



## Documentación del proyecto

Todo está en la carpeta `documentos/`:

- **Entregas de avances:** `docAvance1.docx`, `Avance2_menina_coiffure.pdf`, `Avance3_Menina_Coiffure.pdf` y el Avance 4.
- **Base de datos:** diccionario de datos (`der_diccionario_datos_menina_coiffure.pdf`) y diagrama entidad-relación (`der.html`, `menina_coiffure_db_img.png`).
- **Metodología:** reporte de los Sprints 1 y 2 con el flujo Kanban acumulado. Los Sprints 3 a 5 están en los documentos de los Avances 3 y 4.
- **Instalación paso a paso:** las secciones [Instalación](#instalación) y [Actualización](#actualización) de este README.
- **API:** documentación interactiva en [http://localhost:8000/docs](http://localhost:8000/docs), solo para la administración. La especificación está en `src/resources/openapi/openapi.json` (fuera de `public/` para que no sea pública) y se sirve en `/openapi.json` con la misma protección.
- **Producción:** [deploy/README.md](deploy/README.md).

