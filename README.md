# Menina Coiffure

Sistema web de gestión para Menina Coiffure, salón de estética y peluquería de Villa Elisa: agenda de reservas en el salón y a domicilio, clientes, servicios, personal, pagos, inventario de insumos y reportes. API en Laravel con PostgreSQL y frontend con Vue 3 e Inertia; todo corre con Docker. El código de Laravel está en `src/`.



## 🚀 Instalación

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

El sistema queda en [http://localhost:8000](http://localhost:8000) y la documentación de la API en [http://localhost:8000/docs](http://localhost:8000/docs).

Usuarios de prueba (contraseña `password123`): `admin@meninacoiffure.com` (administración) y `carla@meninacoiffure.com` (profesional).



## 🔄 Actualización

Después de traer cambios del repositorio:

```bash
git fetch
git pull
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app npm install
docker compose exec app npm run build
```



## 🔐 Keycloak (login con proveedor de identidad)

Keycloak corre en su propio contenedor y ya viene configurado desde `keycloak/realm-menina.json` (realm `menina`, cliente `menina-web` con PKCE y usuarios de prueba). No usa la base del salón.

```bash
docker compose up -d keycloak
```

- **Consola de administración:** [http://localhost:8080/admin](http://localhost:8080/admin) (usuario `admin`, contraseña `admin`).
- **Cuenta de una usuaria del realm:** [http://localhost:8080/realms/menina/account](http://localhost:8080/realms/menina/account).
- **Usuarios del realm** (contraseña `password123`): `admin@meninacoiffure.com`, `carla@meninacoiffure.com` y `clienta@meninacoiffure.com`.
- **Elegir el login** en `src/.env`: `AUTH_PROVEEDOR=local` (formulario propio) o `AUTH_PROVEEDOR=keycloak`. Después de cambiarlo: `docker compose exec app php artisan config:clear`.
- **Reimportar el realm** tras editar el JSON (borra los usuarios creados a mano): `docker compose rm -sf keycloak`, `docker volume rm menina_coiffure_keycloak_data` y `docker compose up -d keycloak`.



## 🛠️ Comandos frecuentes

- **Compilar el frontend:** `docker compose exec app npm run build`
- **Aplicar migraciones:** `docker compose exec app php artisan migrate`
- **Reiniciar la base con datos de prueba** (borra todo): `docker compose exec app php artisan migrate:fresh --seed`
- **Cargar datos de volumen para el benchmark:** `docker compose exec app php artisan db:seed --class=VolumenReservasSeeder`
- **Ejecutar las pruebas:** `docker compose exec app php artisan test`
- **Limpiar la caché de rutas:** `docker compose exec app php artisan route:clear`
- **Ver logs:** `docker compose logs -f app`
- **Entrar al contenedor:** `docker compose exec app bash`
- **Detener los servicios:** `docker compose down`
