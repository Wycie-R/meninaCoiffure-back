-- Usuario con el que se conecta la aplicación: sin superusuario.
--
-- Por qué: con el superusuario, cualquier fallo futuro (una inyección SQL, un error en PHP) podría
-- ejecutar comandos del sistema dentro del contenedor de la base (COPY ... PROGRAM), leer archivos
-- o borrar todas las bases. Con este usuario solo puede tocar las tablas de su propia base.
--
-- Quién lo corre:
--   - En un servidor nuevo: solo, al crear la base (deploy/postgres/crear-usuario-app.sh).
--   - Sobre una base que ya existía antes de este cambio: a mano, ver deploy/README.md (sección 10.1).
--
-- Variables que recibe de psql (-v): app_user, app_password y db_name.

-- Sin superusuario y sin poder crear bases, roles ni replicar (es lo que ya hace CREATE ROLE por
-- defecto; se escribe igual para que quede a la vista)
CREATE ROLE :"app_user" LOGIN PASSWORD :'app_password'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;

-- Dueño de la base. Desde PostgreSQL 15 el esquema public pertenece al dueño de la base
-- (pg_database_owner), así que las migraciones que corre la app pueden crear y cambiar tablas
ALTER DATABASE :"db_name" OWNER TO :"app_user";

-- En una base que ya tenía tablas (creadas por el superusuario) pasan a ser de la app; las
-- secuencias de los id cambian de dueño junto con su tabla. En una base nueva no hay tablas y no hace nada.
-- El nombre se guarda en una variable de la sesión porque psql no reemplaza :app_user dentro de $$ ... $$
SELECT set_config('menina.app_user', :'app_user', false);

DO $$
DECLARE
    tabla record;
BEGIN
    FOR tabla IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
        EXECUTE format('ALTER TABLE public.%I OWNER TO %I', tabla.tablename, current_setting('menina.app_user'));
    END LOOP;
END
$$;
