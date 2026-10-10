# Menina Coiffure

Sistema de gestión para Menina Coiffure: API en Laravel y frontend con Vue 3, Inertia.js, Tailwind CSS y Vite. El código de Laravel está en `src/` y todo el entorno corre con Docker.



## ⚙️ Requisitos previos

* [Docker Engine](https://docs.docker.com/engine/install/) y **Docker Compose**
* [Git](https://git-scm.com/)



## 🚀 Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/Wycie-R/meninaCoiffure-back.git
cd meninaCoiffure-back
```

### 2. Configurar variables de entorno

Copia la plantilla base a `src/` y habilita permisos de escritura para que Artisan pueda registrar la clave:

```bash
cp src/.env.example src/.env
sudo chmod 666 src/.env
```

### 3. Instalar dependencias de PHP

Ejecuta Composer como superusuario (`-u 0`) para evitar bloqueos de permisos al crear `/var/www/vendor`:

```bash
docker compose run --rm -u 0 app composer install
```

Reasigna la propiedad de los archivos generados a tu usuario:

```bash
sudo chown -R $USER:$USER src/vendor
```

### 4. Permisos de almacenamiento y caché

Concede acceso a los directorios de logs, sesiones y compilación:

```bash
sudo chmod -R 777 src/storage src/bootstrap/cache
```

### 5. Construir y levantar los contenedores

Inicia los servicios (`app`, `nginx`, `db` y `portainer`) en segundo plano:

```bash
docker compose up -d --build
```

Verifica que estén corriendo:

```bash
docker compose ps
```

> **Importante:** El contenedor `app` incluye **Node.js 22 y npm** para construir el frontend. Si se modifica el `Dockerfile`, vuelve a ejecutar `docker compose up -d --build` para aplicar los cambios.

### 6. Generar la clave de la aplicación

```bash
docker compose exec app php artisan key:generate
```

### 7. Crear las tablas y cargar los datos

Ejecuta las migraciones y todos los seeders (incluyen los usuarios para iniciar sesión):

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

> **Nota:** Si ya tenías la base de datos creada y el login dice "Las credenciales proporcionadas no son válidas.", vuelve a ejecutar `docker compose exec app php artisan db:seed`. Es seguro repetirlo: no duplica datos.

### 8. Instalar y compilar el frontend

Instala las dependencias de JavaScript (Vue 3, Inertia, Tailwind y Vite):

```bash
docker compose exec app npm install
```

Compila los archivos del frontend:

```bash
docker compose exec app npm run build
```

> 💡 Una vez compilado, el sistema queda disponible en [http://localhost:8000](http://localhost:8000).

*(Opcional) Modo desarrollo con recarga automática (HMR):* si tienes Node.js instalado en tu máquina y quieres ver los cambios en tiempo real mientras programas:

```bash
cd src
npm install
npm run dev
```



## 🔐 Acceso al sistema

Las pantallas del sistema (Dashboard, Reservas y Servicios) requieren iniciar sesión en [http://localhost:8000/login](http://localhost:8000/login). Usuarios de prueba creados por los seeders:

| Rol | Correo | Contraseña |
|---|---|---|
| Admin | admin@meninacoiffure.com | password123 |
| Recepcionista | recepcion@meninacoiffure.com | password123 |
| Profesional | carla@meninacoiffure.com | password123 |



## 🌐 Puntos de acceso

- **Sistema (frontend):** [http://localhost:8000](http://localhost:8000)
- **Documentación Swagger / OpenAPI:** [http://localhost:8000/docs](http://localhost:8000/docs)
- **API Reservas:** [http://localhost:8000/api/reservas](http://localhost:8000/api/reservas)
- **Consola Portainer (HTTPS):** [https://localhost:9443](https://localhost:9443)
- **Consola Portainer (HTTP):** [http://localhost:9001](http://localhost:9001)



## 🧪 Pruebas

Para comprobar que la API y las reglas de negocio respondan correctamente:

```bash
docker compose exec app php artisan test
```



## 🛠️ Comandos frecuentes

- **Compilar el frontend:**
  ```bash
  docker compose exec app npm run build
  ```
- **Cargar o actualizar los datos de prueba:**
  ```bash
  docker compose exec app php artisan db:seed
  ```
- **Ver logs en vivo:**
  ```bash
  docker compose logs -f app
  ```
- **Entrar al contenedor:**
  ```bash
  docker compose exec app bash
  ```
- **Detener los servicios:**
  ```bash
  docker compose down
  ```
