\# Entorno base — Menina Coiffure



Stack: PHP 8.4 (php-fpm) + nginx + PostgreSQL 18, todo en Docker. Laravel se instala

dentro del contenedor, no hace falta tenerlo instalado en Windows.



\## 0. Requisitos previos



\- Docker Desktop instalado y corriendo (con integración WSL2 activada, que es el

&#x20; modo por defecto en Windows).

\- Los archivos `Dockerfile`, `docker-compose.yml` y `nginx.conf` ya deben estar en:

&#x20; `C:\\Users\\giova\\OneDrive\\Documentos\\Uninorte\\2026\\2\_semestre\\programación\_iv\\menina\_coiffure\\`

\- Dentro de esa carpeta debe existir una subcarpeta vacía `src\\` (ahí vivirá el

&#x20; proyecto Laravel).



\## 1. Construir la imagen



Abrí una terminal (PowerShell) en la carpeta del proyecto y corré:



```powershell

docker compose build

```



\## 2. Crear el proyecto Laravel dentro del contenedor



Como `src` está vacío, generamos Laravel usando el propio contenedor (así no

necesitás PHP ni Composer instalados en Windows):



```powershell

docker compose run --rm app composer create-project laravel/laravel .

```



Esto puede tardar unos minutos la primera vez. Al terminar, `src\\` va a tener

la estructura completa de Laravel (`app/`, `public/`, `.env`, etc.).



\## 3. Configurar la conexión a PostgreSQL



Abrí `src\\.env` y modificá estas líneas (reemplazan lo que trae por defecto,

que apunta a MySQL/sqlite):



```env

DB\_CONNECTION=pgsql

DB\_HOST=db

DB\_PORT=5432

DB\_DATABASE=menina\_coiffure

DB\_USERNAME=menina

DB\_PASSWORD=menina123

```



`DB\_HOST=db` es el nombre del servicio en `docker-compose.yml`, no `localhost`

ni `127.0.0.1` — dentro de la red de Docker los contenedores se resuelven por

nombre de servicio.



\## 4. Levantar todo



```powershell

docker compose up -d

```



Verificá que los 3 contenedores estén corriendo:



```powershell

docker compose ps

```



Deberías ver `menina\_app`, `menina\_nginx` y `menina\_db` como `running`/`healthy`.



\## 5. Generar la key de la app y migrar



```powershell

docker compose exec app php artisan key:generate

docker compose exec app php artisan migrate

```



\## 6. Verificar



Abrí `http://localhost:8000` en el navegador — deberías ver la pantalla de

bienvenida de Laravel.



\## Comandos que vas a usar seguido



```powershell

\# Ver logs

docker compose logs -f app



\# Entrar a la consola del contenedor

docker compose exec app bash



\# Correr comandos artisan

docker compose exec app php artisan <comando>



\# Correr composer

docker compose exec app composer <comando>



\# Parar todo

docker compose down



\# Parar y borrar también los datos de la BD (cuidado, reinicia Postgres)

docker compose down -v

```



\## Problemas comunes



\- \*\*Puerto 8000 o 5432 ocupado\*\*: si ya tenés algo corriendo en esos puertos en

&#x20; Windows, cambiá el lado izquierdo del mapeo en `docker-compose.yml`, por

&#x20; ejemplo `"8080:80"` o `"5433:5432"`.

\- \*\*Permisos / lentitud rara en `storage/` o `bootstrap/cache/`\*\*: probablemente

&#x20; OneDrive está sincronizando esos archivos mientras Docker escribe en ellos.

&#x20; Pausá la sincronización de OneDrive para esta carpeta durante el desarrollo.

\- \*\*`docker compose build` falla descargando paquetes\*\*: revisá que Docker

&#x20; Desktop tenga acceso a internet y que no haya un firewall corporativo/de

&#x20; universidad bloqueando la descarga de imágenes.

