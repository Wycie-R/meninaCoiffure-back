# Menina Coiffure - Backend API

Guía para el despliegue del entorno de desarrollo mediante Docker, con la estructura del proyecto en `src/`, permisos de usuario en Linux, la ejecución correcta de seeders y la configuración del frontend con Vue, Inertia.js, Tailwind CSS y Vite.



## ⚙️ Requisitos Previos

* [Docker Engine](https://docs.docker.com/engine/install/) y **Docker Compose**
* [Git](https://git-scm.com/)



## 🚀 Pasos de Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/Wycie-R/meninaCoiffure-back.git
cd meninaCoiffure-back
```

### 2. Configurar variables de entorno y permisos

Copia la plantilla base a la carpeta `src/` y habilita permisos de escritura para que Artisan pueda registrar la clave:

```bash
cp src/.env.example src/.env
sudo chmod 666 src/.env
```

### 3. Instalar dependencias de PHP

Ejecutar Composer como superusuario (`-u 0`) para evitar bloqueos de permisos al crear `/var/www/vendor`:

```bash
docker compose run --rm -u 0 app composer install
```

Reasigna la propiedad de los archivos generados a tu usuario del host:

```bash
sudo chown -R $USER:$USER src/vendor
```

### 4. Permisos de almacenamiento y caché de Laravel

Concede acceso a los directorios de logs, sesiones y compilación:

```bash
sudo chmod -R 777 src/storage src/bootstrap/cache
```

### 5. Construir y levantar contenedores

Inicia los servicios (`app`, `db`, `nginx`) en segundo plano:

```bash
docker compose up -d --build
```

Verifica que los tres contenedores estén corriendo:

```bash
docker compose ps
```

> **Importante:** El contenedor `app` incluye **Node.js 22 y npm** para ejecutar y construir el frontend con Vue, Inertia.js, Tailwind CSS y Vite.
>
> Si se realizan cambios en el `Dockerfile`, es necesario reconstruir la imagen para que los cambios se apliquen:
>
> ```bash
> docker compose up -d --build
> ```

### 6. Instalar dependencias del frontend

Instala las dependencias de Vue, Inertia.js, Tailwind CSS y Vite:

```bash
docker compose exec app npm install
```

Para generar la versión compilada del frontend:

```bash
docker compose exec app npm run build
```

### 7. Generar la clave de la aplicación

```bash
docker compose exec app php artisan key:generate
```

### 8. Ejecutar migraciones y poblar la base de datos

Ejecuta el esquema de tablas y el seeder con el nombre de clase exacto:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=AvanceDosSeeder
```

### 8. Instalar y compilar el Frontend (Vue 3 + Inertia + Vite)
Inertia.js requiere dependencias tanto en el **Backend (PHP/Composer)** como en el **Frontend (Node/NPM)**. Se deben ejecutar los comandos dentro del contenedor `app` de Docker:

1. **Asegurar dependencias de PHP (Inertia Laravel adapter):**
   ```bash
   docker compose exec app composer install
   ```

2. **Instalar dependencias de JavaScript (Vue 3, Inertia Vue adapter, Vite, Tailwind):**
   ```bash
   docker compose exec app npm install
   ```

3. **Compilar los assets del frontend:**
   ```bash
   docker compose exec app npm run build
   ```
   > 💡 **Nota:** Una vez compilados con `npm run build`, la interfaz web queda lista y servida automáticamente por Nginx en [http://localhost:8000](http://localhost:8000).

*(Opcional) Modo desarrollo con recarga automática (HMR):*
Si tienes Node.js instalado en tu máquina host y deseas ver los cambios en tiempo real mientras programas componentes:
```bash
cd src
npm install
npm run dev
```

---

## 🧪 Verificación y Pruebas

Para comprobar que la API y las reglas de negocio respondan correctamente:

```bash
docker compose exec app php artisan test
```



## 🌐 Puntos de Acceso

- **Frontend / Entrada base:** [http://localhost:8000](http://localhost:8000)
- **Documentación Swagger / OpenAPI:** [http://localhost:8000/docs](http://localhost:8000/docs)
- **API Reservas:** [http://localhost:8000/api/reservas](http://localhost:8000/api/reservas)
- **Consola Portainer (HTTPS):** [https://localhost:9443](https://localhost:9443)
- **Consola Portainer (HTTP):** [http://localhost:9001](http://localhost:9001)


## 🛠️ Comandos Frecuentes

* **Detener servicios:**

- **Compilar frontend:**
  ```bash
  docker compose exec app npm run build
  ```
- **Detener servicios:**
  ```bash
  docker compose down
  ```
* **Ver logs en vivo:**

  ```bash
  docker compose logs -f app
  ```
* **Acceso interactivo al contenedor:**

  ```bash
  docker compose exec app bash
  ```

