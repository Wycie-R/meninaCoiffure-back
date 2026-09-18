# Menina Coiffure - Backend API

Guía para el despliegue del entorno de desarrollo mediante Docker, con la estructura del proyecto en `src/`, permisos de usuario en Linux y la ejecución correcta de seeders.

---

## ⚙️ Requisitos Previos

- [Docker Engine](https://docs.docker.com/engine/install/) y **Docker Compose**
- [Git](https://git-scm.com/)

---

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

### 6. Generar la clave de la aplicación
```bash
docker compose exec app php artisan key:generate
```

### 7. Ejecutar migraciones y poblar la base de datos
Ejecuta el esquema de tablas y el seeder con el nombre de clase exacto:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=AvanceDosSeeder
```

---

## 🧪 Verificación y Pruebas

Para comprobar que la API y las reglas de negocio respondan correctamente:

```bash
docker compose exec app php artisan test
```

---

## 🌐 Puntos de Acceso

- **Entrada base:** [http://localhost:8000](http://localhost:8000)
- **API Reservas:** [http://localhost:8000/api/reservas](http://localhost:8000/api/reservas)

---

## 🛠️ Comandos Frecuentes

- **Detener servicios:**
  ```bash
  docker compose down
  ```
- **Ver logs en vivo:**
  ```bash
  docker compose logs -f app
  ```
- **Acceso interactivo al contenedor:**
  ```bash
  docker compose exec app bash
  ```
