# Despliegue de Portainer — Menina Coiffure

Este documento describe la integración y puesta en marcha de **Portainer Community Edition (CE)** para la gestión y orquestación visual de los contenedores Docker del proyecto.

---

## 🚀 Despliegue Automatizado (Recomendado para cualquier PC)

Portainer se encuentra integrado directamente en el [`docker-compose.yml`](../docker-compose.yml) principal del proyecto.

Para iniciar todo el stack (incluyendo Portainer, Nginx, Laravel y PostgreSQL), basta con ejecutar desde la raíz del proyecto:

```bash
docker compose up -d
```

Docker Compose se encargará de:
1. Crear la red compartida `menina_net`.
2. Crear y gestionar el volumen persistente `portainer_data`.
3. Conectar el socket del daemon (`/var/run/docker.sock`).
4. Exponer las interfaces web de gestión.

---

## 🌐 Puntos de Acceso a Portainer

* **HTTPS (Seguro - Recomendado):** [https://localhost:9443](https://localhost:9443)
  *(Aceptar la advertencia de certificado autofirmado local).*
* **HTTP:** [http://localhost:9001](http://localhost:9001)

---

## 🔑 Configuración Inicial del Administrador

Al ingresar por primera vez a `https://localhost:9443`:
1. **Crear usuario administrador:**
   * **Usuario:** `admin`
   * **Contraseña:** `meninacoiffure123`
2. Seleccionar el entorno local (**Get Started / Local Docker Environment**).
3. Podrás visualizar y administrar en tiempo real los 4 contenedores del proyecto:
   * `menina_nginx` (Servidor web / Proxy inverso)
   * `menina_app` (Backend Laravel PHP 8.4)
   * `menina_db` (Base de datos relacional PostgreSQL 18)
   * `menina_portainer` (Consola de orquestación)

---

## 🛠️ Método Alternativo (Despliegue Manual por CLI)

Si se desea ejecutar Portainer de manera independiente sin usar Docker Compose:

```bash
# 1. Crear el volumen de persistencia
docker volume create portainer_data

# 2. Ejecutar el contenedor
docker run -d \
  -p 9001:9000 \
  -p 9443:9443 \
  --name menina_portainer \
  --restart=always \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v portainer_data:/data \
  portainer/portainer-ce:latest
```
