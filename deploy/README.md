# Producción en Contabo Cloud VPS 4

Guía para instalar, actualizar y mantener Menina Coiffure en el VPS (4 vCPU, 8 GB de RAM, 100 GB SSD, Ubuntu 24.04).
Todo corre con Docker Compose usando `docker-compose.prod.yml`. El entorno de desarrollo (`docker-compose.yml`) no cambia.

```
Internet ──► :80/:443 Caddy (HTTPS con Let's Encrypt, http → https)
                 │
             HAProxy (leastconn, health check /up)       panel: 127.0.0.1:8404
              ┌──┴───┐
          nginx    nginx_b     (imagen menina/web: public/ y assets)
              │      │
            app    app_b       (imagen menina/app: PHP-FPM con código, vendor y build)
              └──┬───┘
             PostgreSQL 18 (sin puerto publicado)

Portainer: 127.0.0.1:9443 (por túnel SSH)
```

| Archivo | Para qué |
|---|---|
| `Dockerfile.prod` | Imágenes `app` (PHP-FPM) y `web` (nginx) con todo adentro |
| `docker-compose.prod.yml` | Los servicios de producción (proyecto `menina_prod`) |
| `deploy/.env.production.example` | Plantilla de variables; la copia real (`.env.production`) no se sube a Git |
| `deploy/caddy/Caddyfile` | HTTPS y redirección de `www` |
| `deploy/haproxy/haproxy.cfg` | Balanceo y panel de estadísticas |
| `deploy/nginx/default.conf.template` | nginx de cada nodo |
| `deploy/docker/` | Arranque del contenedor, `php.ini` y pool de PHP-FPM |
| `deploy/deploy.sh` | Despliegue y actualización |
| `deploy/primer-arranque.sh` | Solo la primera vez: despliegue, datos del salón y cuenta de la administración |
| `deploy/backup.sh` / `deploy/restaurar.sh` | Backup y restauración de la base |

Todos los comandos de `docker compose` de esta guía se corren desde `/opt/menina`. Para no repetir lo largo, conviene crear un alias en el servidor (se agrega a `~/.bashrc` del usuario `deploy`):

```bash
alias dc='docker compose -f /opt/menina/docker-compose.prod.yml --env-file /opt/menina/deploy/.env.production'
```

---

## 1. Preparar el servidor (una sola vez)

### 1.1 Entrar con llave SSH
Contabo entrega el VPS con usuario `root` y contraseña. Desde tu PC (PowerShell):

```powershell
ssh-keygen -t ed25519                 # si todavía no tienes una llave (Enter en todo)
type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh root@IP_DEL_VPS "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"
ssh root@IP_DEL_VPS                   # ahora entra sin pedir contraseña
```

### 1.2 Sistema, zona horaria y usuario `deploy`
```bash
apt update && apt upgrade -y
timedatectl set-timezone America/Asuncion

adduser deploy                        # pide una contraseña (se usa para sudo)
usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh
cp /root/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys
```

### 1.3 SSH solo con llave y sin root
En Ubuntu 24.04 hay un archivo de cloud-init que puede volver a activar las contraseñas; por eso la configuración va en un archivo propio que se lee primero:

```bash
cat > /etc/ssh/sshd_config.d/00-menina.conf <<'EOF'
PasswordAuthentication no
PermitRootLogin no
EOF
sshd -t && systemctl restart ssh
```

> **Antes de cerrar la sesión de root**, abre otra terminal y comprueba que `ssh deploy@IP_DEL_VPS` entra. Si no, todavía puedes corregirlo desde la sesión abierta.

### 1.4 Firewall
```bash
ufw allow OpenSSH
ufw allow 80,443/tcp
ufw allow 443/udp
ufw enable
```

> **Docker se salta UFW** para los puertos que publica. Por eso `docker-compose.prod.yml` solo publica 80 y 443 hacia afuera, y el panel de HAProxy y Portainer van en `127.0.0.1` (no se ven desde internet aunque UFW no los cubra). Como capa extra, en el panel de Contabo se puede activar su firewall dejando pasar solo 22, 80 y 443.

### 1.5 fail2ban, actualizaciones automáticas y swap
```bash
apt install -y fail2ban unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades      # responder "Sí"

# Swap de 2 GB: un seguro por si algún proceso se dispara (con 8 GB el sistema usa unos 3)
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

### 1.6 Docker Engine y Compose (repositorio oficial de Docker)
```bash
apt install -y ca-certificates curl git
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" > /etc/apt/sources.list.d/docker.list
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
usermod -aG docker deploy
```

Desde acá todo se hace como `deploy` (`ssh deploy@IP_DEL_VPS`).

---

## 2. DNS

En el proveedor del dominio, dos registros `A` hacia la IP del VPS:

| Tipo | Nombre | Valor |
|---|---|---|
| A | `@` | IP del VPS |
| A | `www` | IP del VPS |

Comprobar antes de seguir: `ping meninacoiffure.com` tiene que responder con la IP del VPS. Si Caddy arranca sin DNS, falla al pedir el certificado y Let's Encrypt limita los reintentos.

Si el dominio está en **Cloudflare**, dejar el proxy **desactivado (nube gris)**. Con la nube naranja, Caddy vería las IPs de Cloudflare y el límite de 5 intentos de login por IP bloquearía a todas las clientas a la vez.

---

## 3. Primer despliegue

```bash
sudo mkdir -p /opt/menina && sudo chown deploy:deploy /opt/menina
git clone https://github.com/Wycie-R/meninaCoiffure-back.git /opt/menina
cd /opt/menina
git checkout adaptToProd        # o main, cuando la rama ya esté unida

cp deploy/.env.production.example deploy/.env.production
chmod 600 deploy/.env.production
```

Si el repositorio es privado, `git clone` pide usuario y un *token* de GitHub (Settings → Developer settings → Personal access tokens) en lugar de la contraseña.

### 3.1 Completar `deploy/.env.production`
```bash
openssl rand -hex 32            # una vez para la base y otra para el panel de HAProxy
nano deploy/.env.production
```

- `DOMINIO`, `APP_URL` (`https://` + dominio) y `ACME_EMAIL`.
- `DB_PASSWORD` y `POSTGRES_PASSWORD`: **el mismo valor**.
- `HAPROXY_STATS_PASSWORD`: otro valor distinto.
- `MAIL_*` y `AVISOS_CORREO_DESTINO` (ver sección 9).
- `SALON_WHATSAPP` (solo dígitos, con 595).
- No usar el signo `$` en ningún valor: Docker Compose lo interpreta.

Generar la `APP_KEY` (la primera vez construye la imagen, tarda unos minutos):

```bash
docker compose -f docker-compose.prod.yml --env-file deploy/.env.production run --rm --no-deps app php artisan key:generate --show
```

Copiar lo que imprime (`base64:...`) en `APP_KEY=`. **No cambiarla nunca después**: invalida todas las sesiones y "Seguir conectado".

### 3.2 Arrancar
```bash
bash deploy/primer-arranque.sh
```

Hace, en orden: construye las imágenes, levanta la base, corre las migraciones, levanta todo, comprueba `https://DOMINIO/up`, carga los datos reales del salón (`ProduccionSeeder`: roles, horario lunes a viernes de 8 a 17, métodos de pago, lista de precios 2025 y "Promo miércoles") y pide los datos de la cuenta de la administración.

### 3.3 Backup diario
```bash
crontab -e
# agregar al final:
15 3 * * * bash /opt/menina/deploy/backup.sh >> /opt/menina/deploy/backups/backup.log 2>&1
```

---

## 4. Cuentas del equipo, paso a paso

No hay pantalla pública para crear cuentas con permisos: las clientas crean la suya en `/registro`, pero la administración y las profesionales **solo se crean desde la terminal del servidor**, a la que se entra por SSH con llave. La contraseña se escribe oculta y no queda guardada en el historial.

| Paso | Dónde | Qué |
|---|---|---|
| 1 | Terminal | Cuenta de la administración (ya lo pide `primer-arranque.sh`) |
| 2 | Panel | La dueña entra y carga las fichas en **Personal** (la suya y la de cada profesional, con su correo) |
| 3 | Terminal | Vincular la cuenta de la dueña con su ficha (porque también atiende) |
| 4 | Terminal | Crear la cuenta de cada profesional, vinculada a su ficha |
| 5 | Panel | En **Configuración**, la dueña ajusta los permisos de cada profesional |

```bash
cd /opt/menina

# 1. Administración (si hiciera falta otra)
dc run --rm app php artisan salon:crear-cuenta --rol=admin

# 3. Vincular la cuenta de la dueña con su ficha de personal
dc run --rm app php artisan salon:vincular-ficha correo-de-la-cuenta@gmail.com correo-de-su-ficha@gmail.com

# 4. Cada profesional (su ficha tiene que existir en Personal)
dc run --rm app php artisan salon:crear-cuenta --rol=profesional --email=carla@gmail.com --nombre="Carla" --personal-email=carla@gmail.com
```

El comando rechaza contraseñas de menos de 12 caracteres, correos repetidos, roles que no sean `admin` o `profesional`, fichas inexistentes y fichas que ya tienen cuenta.

**¿Y por base de datos?** No se recomienda. Habría que escribir a mano el hash bcrypt de la contraseña, el `rol_id` y el `personal_id`, sin ninguna validación, y un error deja una cuenta que no entra o con permisos de más. El comando hace lo mismo con validaciones.

**Si alguien olvida su contraseña:** "¿Olvidaste tu contraseña?" en `/login` (necesita el correo configurado).

---

## 5. Actualizar

```bash
cd /opt/menina
bash deploy/deploy.sh
```

Trae los cambios (`git pull --ff-only`), reconstruye las imágenes, migra una sola vez, recrea los contenedores y comprueba `/up`. Antes de una actualización grande conviene correr `bash deploy/backup.sh` y, si es posible, un snapshot desde el panel de Contabo.

Después de cambiar `deploy/.env.production` también hay que correr `deploy.sh`: los contenedores cachean la configuración al arrancar y solo toman los valores nuevos al recrearse.

---

## 6. Portainer y estadísticas de HAProxy (túnel SSH)

Los dos paneles solo escuchan en `127.0.0.1` del servidor. Desde tu PC:

```powershell
ssh -L 9443:127.0.0.1:9443 -L 8404:127.0.0.1:8404 deploy@IP_DEL_VPS
```

Con esa sesión abierta:
- **Portainer:** https://localhost:9443 (el navegador avisa del certificado propio de Portainer: aceptar). La primera vez pide crear el usuario administrador; **hay que hacerlo en los primeros 5 minutos** después de levantar Portainer o se bloquea por seguridad (`dc restart portainer` para reintentar).
- **HAProxy:** http://localhost:8404/stats con el usuario `admin` y `HAPROXY_STATS_PASSWORD`. Muestra el estado de cada nodo, las sesiones, la cola y permite poner un nodo en mantenimiento (MAINT).

---

## 7. Logs

```bash
dc logs -f app app_b haproxy caddy     # en vivo
dc logs --tail=200 app                 # las últimas 200 líneas
dc ps                                  # estado de los contenedores
```

Laravel escribe sus errores en la salida estándar (`LOG_CHANNEL=stderr`), así que se ven acá. Los logs rotan solos (10 MB × 3 archivos por servicio).

---

## 8. Monitoreo

Crear un monitor HTTP gratuito en [UptimeRobot](https://uptimerobot.com) apuntando a `https://DOMINIO/up` cada 5 minutos, con aviso al correo del equipo.

---

## 9. Correo

Gmail con una **contraseña de aplicación** (Cuenta de Google → Seguridad → Verificación en 2 pasos → Contraseñas de aplicaciones):

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=cuenta@gmail.com
MAIL_PASSWORD=la-contraseña-de-aplicación
MAIL_FROM_ADDRESS=cuenta@gmail.com
AVISOS_CORREO_DESTINO=correo-de-la-duena@gmail.com
```

Probar (después de `bash deploy/deploy.sh` para que tome los valores):

```bash
dc run --rm app php artisan correo:probar
```

Si el envío falla por red (Contabo no debería bloquear el 587), la alternativa es un proveedor con API HTTPS como Resend. Requiere instalar el paquete `resend/resend-laravel` (no está instalado) y usar `MAIL_MAILER=resend`.

---

## 10. Backups, snapshot y restauración

| Qué | Cubre | Limitación |
|---|---|---|
| **Snapshot de Contabo** (panel → Snapshots) | Todo el servidor en un momento dado: sistema, Docker, base y certificados | El plan permite pocos y cada nuevo reemplaza al anterior; hay que hacerlo a mano antes de cambios grandes |
| **`deploy/backup.sh`** (cron diario) | Solo la base, con 14 días de historia | Si el VPS se pierde entero, los dumps locales se pierden con él |
| **`backup.sh` + rclone** | Copia de cada dump fuera del servidor | Hay que configurar un remoto (Cloudflare R2 o Backblaze B2 tienen plan gratis) |

Con un solo snapshot, el backup diario es lo que permite recuperar, por ejemplo, una cita borrada por error hace tres días. Para la copia externa: `sudo apt install rclone`, `rclone config` y crear un remoto llamado exactamente **`respaldo`**; `backup.sh` lo detecta solo.

### Restaurar
```bash
ls deploy/backups/
bash deploy/restaurar.sh deploy/backups/menina_2026-11-20_0315.dump
# pide escribir RESTAURAR para confirmar
```

Detiene los nodos, reemplaza todos los datos por los del backup y vuelve a levantarlos. Funciona también sobre una base vacía (por ejemplo, en un servidor nuevo después de `primer-arranque.sh`).

---

## 11. Modo demostración (Avance 4: `/balanceo` y benchmark)

En producción `/balanceo` y `/api/balanceo/ping` responden 404 y las respuestas no traen la cabecera `X-Servido-Por` (el ping puede dejar ocupados todos los procesos de PHP). Para la exposición:

```bash
nano deploy/.env.production     # APP_DEMO=true
bash deploy/deploy.sh
# ... demo, benchmark, capturas del panel de HAProxy por el túnel SSH ...
nano deploy/.env.production     # APP_DEMO=false
bash deploy/deploy.sh
```

---

## 12. Problemas comunes

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| `deploy.sh` dice que `/up` no responde | DNS sin propagar o certificado pendiente | `dc logs --tail=100 caddy`; comprobar el DNS con `ping` |
| Error 503 de HAProxy | Los dos nodos caídos | `dc ps` y `dc logs --tail=100 app app_b` |
| Error 500 en todas las páginas | `APP_KEY` vacía o base sin migrar | Revisar `.env.production` y `dc run --rm app php artisan migrate:status` |
| Los cambios del `.env.production` no se aplican | La configuración se cachea al arrancar | `bash deploy/deploy.sh` |
| No llegan los correos | Credenciales de Gmail o contraseña de aplicación | `dc run --rm app php artisan correo:probar` y `dc logs app` |
| Portainer dice "timed out" | No se creó el admin en 5 minutos | `dc restart portainer` y crearlo enseguida |
