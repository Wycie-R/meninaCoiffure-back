# Instalación del entorno — Menina Coiffure

Stack: PHP 8.4 (php-fpm) + nginx + PostgreSQL 18, todo en Docker.

## Requisitos previos

- **Docker Desktop** instalado y corriendo (Windows: con integración WSL2
  activada, que es el modo por defecto).
- Estos 3 archivos, descargados en una carpeta nueva y vacía:
  - `Dockerfile`
  - `docker-compose.yml`
  - `nginx.conf`
- Dentro de esa misma carpeta, crear una subcarpeta vacía llamada `src`.

La estructura final debe quedar así:

```
menina_coiffure/
├── Dockerfile
├── docker-compose.yml
├── nginx.conf
└── src/          ← vacía por ahora
```

> **Tip:** si tu carpeta está dentro de OneDrive/Google Drive/Dropbox,
> pausá la sincronización mientras trabajás — puede hacer que la
> instalación de Laravel sea muchísimo más lenta o falle por timeout.
> También conviene excluir la carpeta del antivirus (Windows Defender u
> otro) por la misma razón.

## Pasos

1. Abrí una terminal (PowerShell en Windows, Terminal en Mac/Linux) **parado
   en la carpeta `menina_coiffure`** (donde está el `docker-compose.yml`).

2. Construí la imagen:

   ```bash
   docker compose build
   ```

3. Instalá Laravel dentro del contenedor (como `src` está vacío, no
   necesitás tener PHP ni Composer en tu máquina):

   ```bash
   docker compose run --rm app composer create-project laravel/laravel .
   ```

   Puede tardar varios minutos la primera vez.

4. Abrí `src/.env` y configurá la conexión a PostgreSQL, reemplazando las
   líneas `DB_*` que trae por defecto:

   ```env
   DB_CONNECTION=pgsql
   DB_HOST=db
   DB_PORT=5432
   DB_DATABASE=menina_coiffure
   DB_USERNAME=menina
   DB_PASSWORD=menina123
   ```

   `DB_HOST=db` es el nombre del servicio en `docker-compose.yml`, no
   `localhost` — dentro de la red de Docker los contenedores se resuelven
   por nombre de servicio.

5. Levantá todo:

   ```bash
   docker compose up -d
   ```

6. Verificá que los contenedores estén corriendo:

   ```bash
   docker compose ps
   ```

   Deberían verse `menina_haproxy`, `menina_nginx`, `menina_app` (nodo A),
   `menina_nginx_b`, `menina_app_b` (nodo B) y `menina_db` como `running`
   (y `menina_db` como `healthy`). El puerto 8000 lo atiende HAProxy, que
   reparte entre los dos nodos.

7. Generá la key de la app y corré las migraciones:

   ```bash
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate
   ```

8. Abrí `http://localhost:8000` en el navegador — si ves la pantalla de
   bienvenida de Laravel, quedó todo funcionando.

## Comandos que se usan seguido

```bash
docker compose logs -f app          # ver logs
docker compose exec app bash        # entrar a la consola del contenedor
docker compose exec app php artisan <comando>
docker compose exec app composer <comando>
docker compose down                 # parar todo
docker compose down -v              # parar y borrar también los datos de la BD
```

## Problemas comunes

- **Puerto 8000 o 5432 ocupado**: cambiá el número de la izquierda en el
  mapeo de puertos dentro de `docker-compose.yml` (ej. `"8080:80"`).
- **Timeout instalando Laravel / muy lento**: casi siempre es un
  antivirus o un cliente de nube (OneDrive, Drive) escaneando/sincronizando
  cada archivo. Pausalo o excluí la carpeta mientras instalás.
- **`docker compose build` falla descargando paquetes**: revisá conexión a
  internet y que no haya un firewall (corporativo/universidad) bloqueando
  la descarga de imágenes de Docker.
- **Error de Postgres al arrancar (`unhealthy`)**: correr
  `docker compose logs db` para ver el motivo exacto antes de asumir nada.
