# Menina Coiffure - Backend API

Guía de instalación, configuración, despliegue y pruebas del backend.
Desarrollado en **Laravel** y containerizado mediante **Docker**.

---

## 📋 Tabla de Contenidos

1. [Descripción General](#-descripción-general)
2. [Requisitos Previos](#-requisitos-previos)
3. [Guía de Instalación Paso a Paso](#-guía-de-instalación-paso-a-paso)
   - [Paso 1: Clonar el Repositorio](#paso-1-clonar-el-repositorio)
   - [Paso 2: Acceder al Directorio](#paso-2-acceder-al-directorio)
   - [Paso 3: Instalar Dependencias con Composer](#paso-3-instalar-dependencias-con-composer)
   - [Paso 4: Construir y Levantar Contenedores](#paso-4-construir-y-levantar-contenedores)
   - [Paso 5: Generar la Clave de la Aplicación](#paso-5-generar-la-clave-de-la-aplicación)
   - [Paso 6: Migraciones y Poblado de Datos (Seeders)](#paso-6-migraciones-y-poblado-de-datos-seeders)
4. [Verificación y Pruebas](#-verificación-y-pruebas)
5. [Endpoints y Rutas Principales](#-endpoints-y-rutas-principales)
6. [Comandos Frecuentes y Mantenimiento](#-comandos-frecuentes-y-mantenimiento)
7. [Solución de Problemas Comunes](#-solución-de-problemas-comunes)

---

## 📖 Descripción General

Este backend gestiona los servicios, clientes y reservas para **Menina Coiffure**. 
Es una API RESTful estructurada construida con el framework Laravel y preparada para ejecutarse en entornos aislados y reproducibles con Docker y Docker Compose.

---

## ⚙️ Requisitos Previos

Antes de comenzar, asegúrate de contar con las siguientes herramientas instaladas en tu sistema:

- **[Git](https://git-scm.com/)** (v2.30 o superior)
- **[Docker](https://www.docker.com/)** (Docker Desktop en Windows/macOS o Docker Engine en Linux)
- **Docker Compose** (integrado en versiones recientes de Docker o standalone `docker-compose`)

> **Nota:** No es estrictamente obligatorio tener PHP ni Composer instalados en el host, ya que todas las dependencias y comandos se ejecutan a través de los contenedores Docker.

---

## 🚀 Guía de Instalación Paso a Paso

Sigue rigurosamente el orden de los siguientes pasos para configurar el proyecto desde cero:

### Paso 1: Clonar el Repositorio

Dirígete al directorio de tu preferencia, abre una terminal o línea de comandos y descarga el código fuente oficial:

```bash
git clone https://github.com/Wycie-R/meninaCoiffure-back.git
```

### Paso 2: Acceder al Directorio

Ingresa a la carpeta del proyecto recién clonado:

```bash
cd menina_coiffure
```

> *(Opcional, ya que en teoría ya tenemos este archivo, pero si quieres modificarlo sigue estos pasos)* Si tu entorno requiere un archivo `.env`,
> asegúrate de crearlo a partir del archivo de ejemplo antes de levantar los servicios:
> ```bash
> cp .env.example .env
> ```

### Paso 3: Instalar Dependencias con Composer

Descargar todas las dependencias del proyecto sin necesidad de tener PHP instalado localmente:

```bash
docker compose run --rm app composer install
```

### Paso 4: Construir y Levantar Contenedores

Compila las imágenes e inicia los servicios (aplicación, servidor web y base de datos) en segundo plano (*detached mode*):

```bash
docker compose up -d --build
```

Para verificar que los contenedores estén corriendo correctamente, puedes ejecutar (deberías ver que están 'UP'):

```bash
docker compose ps
```

### Paso 5: Generar la Clave de la Aplicación

Genera la clave de encriptación (`APP_KEY`) propia de Laravel dentro del contenedor:

```bash
docker compose exec app php artisan key:generate
```

### Paso 6: Migraciones y Poblado de Datos (Seeders). Los seeders son básicamente 'pruebas'

Ejecuta el esquema de base de datos y añade los datos iniciales requeridos utilizando el seeder asignado:

```bash
docker compose exec app php artisan migrate --seed --seeder=AvanceDosSeeder
```

> **Nota de sintaxis:** El comando original indicado es `Avance DosSeeder`. Si tu terminal interpreta el espacio, enciérralo entre comillas (`--seeder="Avance DosSeeder"`) o utiliza el nombre de clase exacto según tu convención (ej. `AvanceDosSeeder`).

---

## 🧪 Verificación y Pruebas

Para garantizar que todos los módulos y la suite de pruebas automatizadas funcionen sin errores:

```bash
docker compose exec app php artisan test
```

Si todos los tests devuelven un estado exitoso (`PASS`), la instalación ha culminado con éxito.

---

## 🌐 Endpoints y Rutas Principales

Una vez en ejecución, los siguientes servicios estarán accesibles en tu máquina local:

| Servicio | URL | Descripción |
| :--- | :--- | :--- |
| **Página Principal** | [http://localhost:8000](http://localhost:8000) | Entrada base del backend / Laravel welcome |
| **API Reservas** | [http://localhost:8000/api/reservas](http://localhost:8000/api/reservas) | Endpoint para consultar y gestionar reservas |

---

## 🛠️ Comandos Frecuentes y Mantenimiento

Lista de comandos para el día a día con Docker:

- **Detener los servicios:**
  ```bash
  docker compose down
  ```

- **Ver registros y logs en tiempo real:**
  ```bash
  docker compose logs -f app
  ```

- **Acceder a la terminal interactiva del contenedor:**
  ```bash
  docker compose exec app bash
  ```

- **Limpiar cachés de Laravel:**
  ```bash
  docker compose exec app php artisan optimize:clear
  ```

---

## ❓ Solución de Problemas Comunes

1. **Error de permisos en directorios (`storage` o `bootstrap/cache`):**
   ```bash
   docker compose exec app chmod -R 775 storage bootstrap/cache
   ```

2. **Error de conexión a la base de datos:**
   - Asegúrate de que el contenedor de la base de datos haya terminado de inicializarse antes de lanzar las migraciones.
   - Verifica que las credenciales en tu `.env` coincidan con los valores definidos en `docker-compose.yml`.

3. **Conflicto de puertos (`Port 8000 already in use`):**
   - Comprueba si tienes otro proceso usando el puerto 8000 y finalízalo, o bien modifica el mapeo de puertos en el archivo `docker-compose.yml`.
