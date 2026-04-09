# Guía de Migración: Laravel → NestJS Dockerizado

> **Fecha:** 2026-04-08  
> **Versión:** 1.0  
> **Contexto:** Migrar Certificate Manager desde Laravel (producción) a NestJS dockerizado

---

## Tabla de Contenidos

1. [Visión General](#1-visión-general)
2. [Compatibilidad de Archivos (Zero-Friction)](#2-compatibilidad-de-archivos-zero-friction)
3. [Fase 1: Preparación del Entorno](#3-fase-1-preparación-del-entorno)
4. [Fase 2: Migración de Base de Datos](#4-fase-2-migración-de-base-de-datos)
5. [Fase 3: Migración de Archivos](#5-fase-3-migración-de-archivos)
6. [Fase 4: Configuración de Variables de Entorno](#6-fase-4-configuración-de-variables-de-entorno)
7. [Fase 5: Validación Pre-Producción](#7-fase-5-validación-pre-producción)
8. [Fase 6: Switchover a Producción](#8-fase-6-switchover-a-producción)
9. [Plan de Rollback](#9-plan-de-rollback)
10. [Preguntas Frecuentes](#10-preguntas-frecuentes)

---

## 1. Visión General

### Arquitectura Actual (Laravel)
```
Servidor Producción
├── Laravel (PHP-FPM + Nginx/Apache)
├── MariaDB (directo en host o servicio)
└── storage/app/
    ├── attachments/companies/{id}/{year}/{month}/{dniDv}/archivo.pdf
    └── pdf/
```

### Arquitectura Destino (NestJS Docker)
```
Docker Host
├── backnest-api      (Node.js + Fastify)
├── backnest-mariadb  (MariaDB 11)
├── backnest-redis    (Redis 7)
└── ./storage/app/    (bind mount al contenedor)
    ├── attachments/  → /app/storage/app/attachments (dentro del contenedor)
    └── pdf/          → /app/storage/app/pdf
```

### ¿Qué cambia y qué NO cambia?

| Aspecto | ¿Cambia? | Detalle |
|---------|----------|---------|
| Estructura de archivos en disco | ❌ No | Misma estructura `companies/{id}/{year}/{month}/{dniDv}/` |
| `file_path` en BD | ❌ No | Sigue siendo `companies/1/2025/04/100092351/doc.pdf` |
| URL pública de archivos | ❌ No | Sigue siendo `{APP_URL}/attachments/{file_path}` |
| Schema de BD | ❌ No | TypeORM con `synchronize: false` |
| Autenticación | ✅ Sí | Passport → JWT. **Los usuarios deben re-loguearse** |
| Prefijo API | ❌ No | Sigue siendo `/api/v1/` |
| Motor de BD | ❌ No | MariaDB → MariaDB |

---

## 2. Compatibilidad de Archivos (Zero-Friction)

### Cómo funciona en Laravel

```
Laravel filesystem config:
  disk "attachment" → root: storage/app/attachments/
  
Symlink:
  public/attachments → storage/app/attachments

URL resultante:
  https://api.ejemplo.com/attachments/companies/1/2025/04/100092351/doc.pdf
```

### Cómo funciona en NestJS

```typescript
// main.ts — @fastify/static (registrado FUERA del global prefix api/v1)
await app.register(fastifyStatic, {
  root: join(storagePath, 'attachments'),  // → /app/storage/app/attachments
  prefix: '/attachments/',                  // → sirve en /attachments/{file_path}
});

await app.register(fastifyStatic, {
  root: join(storagePath, 'pdf'),
  prefix: '/pdf/',
});
```

### Resultado: misma URL, misma estructura

```
Frontend construye la URL así:
  const url = `${this.http.getAppUrl()}/attachments/${file.file_path}`;
  
Ejemplo:
  https://api.ejemplo.com/attachments/companies/1/2025/04/100092351/doc.pdf

Fastify busca el archivo en:
  /app/storage/app/attachments/companies/1/2025/04/100092351/doc.pdf

Que gracias al bind mount es:
  ./storage/app/attachments/companies/1/2025/04/100092351/doc.pdf
```

**✅ Solo necesitas copiar los directorios de Laravel y todo funciona.**

---

## 3. Fase 1: Preparación del Entorno

### 3.1 Pre-requisitos en el servidor destino

```bash
# Docker y Docker Compose
docker --version       # >= 24.0
docker compose version # >= 2.20

# Espacio en disco (verificar)
df -h /var/lib/docker
```

### 3.2 Clonar y configurar el proyecto

```bash
# En el servidor de producción (o staging)
git clone <repo-url> /opt/certificate-manager
cd /opt/certificate-manager/backnest

# Crear directorios de storage
mkdir -p storage/app/attachments
mkdir -p storage/app/pdf
mkdir -p docker/initdb

# Copiar .env
cp .env.example .env
# Editar con los valores de producción (ver Fase 4)
```

---

## 4. Fase 2: Migración de Base de Datos

### Opción A: Importación automática (primer arranque)

```bash
# 1. Exportar BD desde el servidor Laravel
mysqldump --single-transaction --routines --triggers \
  --host=127.0.0.1 --port=3306 \
  --user=root --password=TU_PASSWORD \
  nombre_base_datos > 001-dump-produccion.sql

# 2. Copiar el dump al servidor NestJS
scp 001-dump-produccion.sql user@nest-server:/opt/certificate-manager/backnest/docker/initdb/

# 3. Arrancar (MariaDB importará el dump automáticamente en el primer boot)
docker compose up -d mariadb
docker compose logs -f mariadb  # Verificar que termina la importación

# 4. Verificar
docker exec -it backnest-mariadb mariadb -u root -pTU_PASSWORD nombre_bd -e "SHOW TABLES;"
docker exec -it backnest-mariadb mariadb -u root -pTU_PASSWORD nombre_bd -e "SELECT COUNT(*) FROM file_managers;"
```

### Opción B: Importación manual (si el volumen ya existe)

```bash
# Si el contenedor MariaDB ya arrancó antes con un volumen existente,
# docker-entrypoint-initdb.d no se ejecuta. Importar manualmente:

docker exec -i backnest-mariadb mariadb \
  -u root -pTU_PASSWORD nombre_bd < docker/initdb/001-dump-produccion.sql
```

### Opción C: Ambos apps leen la misma BD (período de transición)

Si Laravel y NestJS corren en el **mismo servidor**, pueden compartir la misma instancia de MariaDB:

```yaml
# docker-compose.yml — NO levantar mariadb en Docker
# Cambiar en environment del servicio api:
environment:
  - DB_HOST=host.docker.internal  # Accede al MariaDB del host
  - DB_PORT=3306
```

> ⚠️ **Nota sobre `synchronize`**: TypeORM está configurado con `synchronize: false`. 
> NestJS **nunca** modificará el esquema de la BD. Es seguro apuntar a la BD de producción.

---

## 5. Fase 3: Migración de Archivos

### Usando el script automatizado

```bash
# Desde el directorio backnest/
chmod +x scripts/migrate-from-laravel.sh

./scripts/migrate-from-laravel.sh \
  --laravel-path /var/www/certificate-manager/backend \
  --db-user root \
  --db-pass TU_PASSWORD \
  --db-name nombre_bd
```

### Manualmente con rsync

```bash
# Desde el servidor Laravel al servidor NestJS
rsync -avz --progress \
  /var/www/certificate-manager/backend/storage/app/attachments/ \
  /opt/certificate-manager/backnest/storage/app/attachments/

rsync -avz --progress \
  /var/www/certificate-manager/backend/storage/app/pdf/ \
  /opt/certificate-manager/backnest/storage/app/pdf/
```

### Si están en el mismo servidor

```bash
# Opción 1: Copiar (duplica datos, más seguro)
cp -r /var/www/laravel/storage/app/attachments/* /opt/backnest/storage/app/attachments/
cp -r /var/www/laravel/storage/app/pdf/*         /opt/backnest/storage/app/pdf/

# Opción 2: Symlink (no duplica, comparten datos)
# PRECAUCIÓN: Si eliminas desde NestJS, también se elimina para Laravel
ln -s /var/www/laravel/storage/app/attachments /opt/backnest/storage/app/attachments
ln -s /var/www/laravel/storage/app/pdf         /opt/backnest/storage/app/pdf
```

### Verificar la estructura final

```bash
tree -L 4 storage/app/attachments/ | head -20
# Debería mostrar:
# storage/app/attachments/
# └── companies/
#     ├── 1/
#     │   └── 2025/
#     │       ├── 01/
#     │       ├── 02/
#     │       └── 04/
#     ├── 2/
#     ...
```

---

## 6. Fase 4: Configuración de Variables de Entorno

### Mapeo Laravel `.env` → NestJS `.env`

| Laravel `.env` | NestJS `.env` | Notas |
|---|---|---|
| `APP_URL=https://api.ejemplo.com` | `APP_URL=https://api.ejemplo.com` | Mismo dominio |
| `DB_CONNECTION=mysql` | `DB_TYPE=mariadb` | Cambio de driver |
| `DB_HOST=127.0.0.1` | `DB_HOST=mariadb` | Nombre del servicio Docker |
| `DB_PORT=3306` | `DB_PORT=3306` | Sin cambio |
| `DB_DATABASE=nombre_bd` | `DB_DATABASE=nombre_bd` | Sin cambio |
| `DB_USERNAME=root` | `DB_USERNAME=root` | Sin cambio |
| `DB_PASSWORD=secret` | `DB_PASSWORD=secret` | Sin cambio |
| *(no existe)* | `DB_SYNCHRONIZE=false` | **Crítico**: no modificar esquema |
| *(Passport)* | `JWT_SECRET=clave_fuerte_aquí` | **Generar nueva**: `openssl rand -hex 64` |
| *(Passport)* | `JWT_EXPIRES_IN=90d` | Equivalente a token expiration |
| `MAIL_HOST` | `MAIL_HOST` | Sin cambio |
| `MAIL_PORT` | `MAIL_PORT` | Sin cambio |
| `MAIL_USERNAME` | `MAIL_USER` | ⚠️ **Nombre diferente** |
| `MAIL_PASSWORD` | `MAIL_PASS` | ⚠️ **Nombre diferente** |
| `MAIL_FROM_ADDRESS` | `MAIL_FROM_ADDRESS` | Sin cambio |
| `MAIL_FROM_NAME` | `MAIL_FROM_NAME` | Sin cambio |
| `PAT_EXPIRATION_DAYS` | `TOKEN_EXPIRATION_DAYS` | Renombrado |
| `WEBHOOK_*` | `WEBHOOK_*` | Sin cambios significativos |
| *(no existe)* | `REDIS_HOST=redis` | Nombre del servicio Docker |
| *(no existe)* | `SWAGGER_ENABLED=false` | Desactivar en producción |

### Ejemplo de `.env` de producción

```env
APP_NAME="Certificate Manager API"
APP_ENV=production
APP_PORT=3000
APP_URL=https://api.ejemplo.com
APP_TIMEZONE=America/Bogota

DB_TYPE=mariadb
DB_HOST=mariadb
DB_PORT=3306
DB_USERNAME=cm_user
DB_PASSWORD=CONTRASEÑA_FUERTE_AQUÍ
DB_DATABASE=certificate_manager
DB_SYNCHRONIZE=false
DB_LOGGING=false

JWT_SECRET=GENERAR_CON_openssl_rand_-hex_64
JWT_EXPIRES_IN=90d

MAIL_HOST=smtp.proveedor.com
MAIL_PORT=587
MAIL_USER=usuario@ejemplo.com
MAIL_PASS=contraseña_email

REDIS_HOST=redis
REDIS_PORT=6379

SWAGGER_ENABLED=false
```

---

## 7. Fase 5: Validación Pre-Producción

### 7.1 Levantar el stack completo

```bash
cd /opt/certificate-manager/backnest
docker compose up -d
docker compose ps    # Verificar que todo esté "running"
docker compose logs -f api  # Ver logs del arranque
```

### 7.2 Smoke tests

```bash
# 1. Health check (la API responde)
curl -s http://localhost:3000/api/v1 | head

# 2. Login (autenticación JWT funciona)
curl -s -X POST http://localhost:3000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@ejemplo.com","password":"test123"}' | jq .

# 3. Archivos estáticos (attachments se sirven)
# Tomar un file_path real de la BD:
docker exec backnest-mariadb mariadb -u root -p$DB_PASSWORD $DB_DATABASE \
  -e "SELECT file_path FROM file_managers LIMIT 3;"

# Probar acceso directo:
curl -I http://localhost:3000/attachments/companies/1/2025/04/100092351/doc.pdf
# Debe devolver: HTTP/1.1 200 OK + Content-Type correcto

# 4. PDFs se sirven
curl -I http://localhost:3000/pdf/nombre-del-pdf.pdf

# 5. Listar certificados (endpoint funcional)
TOKEN=$(curl -s -X POST http://localhost:3000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@ejemplo.com","password":"test123"}' | jq -r '.dataRecords.token')

curl -s http://localhost:3000/api/v1/certificate-request \
  -H "Authorization: Bearer $TOKEN" | jq '.dataRecords.data | length'
```

### 7.3 Verificar desde el frontend

1. En `environment.prod.ts`, confirmar que `APPURL` apunta al mismo dominio
2. Desplegar el frontend apuntando al NestJS en staging
3. Probar:
   - Login ✅
   - Listar solicitudes ✅
   - **Descargar un archivo adjunto** ✅ (clic en "Descargar archivo")
   - **Ver un PDF** ✅ (clic en documento PDF)
   - Subir un nuevo archivo ✅

---

## 8. Fase 6: Switchover a Producción

### Estrategia recomendada: Blue-Green con Nginx

```
                    ┌──────────────┐
                    │    Nginx     │
                    │ Reverse Proxy│
   Internet ──────►│              │
                    │   :443/80   │
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              ▼                         ▼
      ┌───────────────┐       ┌───────────────┐
      │   Laravel     │       │   NestJS      │
      │  (Blue/Old)   │       │  (Green/New)  │
      │   :9000       │       │   :3000       │
      └───────────────┘       └───────────────┘
              │                         │
              └─────────┬───────────────┘
                        ▼
                 ┌─────────────┐
                 │  MariaDB    │
                 │  (shared)   │
                 └─────────────┘
```

### Paso a paso

```bash
# 1. Reducir TTL de DNS a 60s (3 días antes)
# En tu proveedor de DNS, cambiar el TTL del registro A

# 2. Backup final antes del switch
mysqldump --single-transaction -u root -p nombre_bd > backup_pre_switch_$(date +%Y%m%d_%H%M).sql
rsync -av storage/app/attachments/ /backups/attachments_pre_switch/

# 3. Configurar Nginx para enrutar a NestJS
cat > /etc/nginx/sites-available/api-certs.conf << 'EOF'
upstream nestjs {
    server 127.0.0.1:3000;
}

server {
    listen 443 ssl http2;
    server_name api.ejemplo.com;

    ssl_certificate     /etc/ssl/certs/api.ejemplo.com.pem;
    ssl_certificate_key /etc/ssl/private/api.ejemplo.com.key;

    # Archivos estáticos (attachments y pdf)
    location /attachments/ {
        proxy_pass http://nestjs;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /pdf/ {
        proxy_pass http://nestjs;
        proxy_set_header Host $host;
    }

    # API
    location /api/v1/ {
        proxy_pass http://nestjs;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
    }

    # WebSocket (si se usa para notificaciones)
    location /socket.io/ {
        proxy_pass http://nestjs;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }
}
EOF

# 4. Validar y recargar Nginx
nginx -t
systemctl reload nginx

# 5. Verificar que el tráfico llega a NestJS
curl -I https://api.ejemplo.com/api/v1
# Debe devolver respuesta de NestJS

# 6. Monitorear logs
docker compose logs -f api
```

### Período de coexistencia (opcional)

Si quieres migrar gradualmente, puedes mantener ambos backends:

```nginx
# Rutas ya migradas → NestJS
location /api/v1/auth/ { proxy_pass http://nestjs; }
location /api/v1/certificate-request { proxy_pass http://nestjs; }
location /attachments/ { proxy_pass http://nestjs; }

# Rutas pendientes → Laravel (fallback)
location /api/ { proxy_pass http://laravel; }
```

---

## 9. Plan de Rollback

| Escenario | Acción | Tiempo |
|-----------|--------|--------|
| NestJS no arranca | Verificar logs, arreglar `.env` | 5 min |
| Archivos no se sirven | Verificar bind mounts y permisos | 5 min |
| Bug en endpoint crítico | Cambiar Nginx a Laravel | < 1 min |
| BD corrupta | Restaurar dump pre-switch | 10-30 min |
| Fallo total | Restaurar DNS a Laravel | TTL (60s si lo redujiste) |

### Rollback rápido (Nginx)

```bash
# Cambiar todas las rutas de vuelta a Laravel
cat > /etc/nginx/sites-available/api-certs.conf << 'EOF'
upstream laravel {
    server 127.0.0.1:9000;  # o el puerto de Laravel
}
server {
    listen 443 ssl http2;
    server_name api.ejemplo.com;
    location / { proxy_pass http://laravel; }
}
EOF
nginx -t && systemctl reload nginx
```

---

## 10. Preguntas Frecuentes

### ¿Los usuarios deben hacer algo diferente?

Solo **re-loguearse**. Laravel usa Passport (tokens opacos) y NestJS usa JWT. Los tokens existentes no son válidos en NestJS.

### ¿Puedo apuntar NestJS a la misma BD de Laravel sin riesgo?

Sí. `synchronize: false` está configurado en TypeORM. NestJS **no modifica** el esquema. Solo lee y escribe datos usando las tablas existentes.

### ¿Qué pasa con los archivos subidos durante la transición?

Si ambos backends comparten el mismo directorio de storage (symlink o bind mount al mismo path), los archivos son accesibles por ambos. Si están separados, necesitas sincronizar con `rsync`.

### ¿Bind mounts o named volumes?

| Tipo | Para archivos (attachments/pdf) | Para BD (MariaDB) |
|------|------|------|
| **Bind mount** | ✅ Recomendado | ❌ No recomendado |
| **Named volume** | ❌ Difícil de inspeccionar | ✅ Recomendado |

**Archivos**: Bind mounts permiten `rsync`, `cp`, backups directos, inspección con `ls/tree`.  
**BD**: Named volumes evitan conflictos de permisos con el motor MariaDB.

### ¿Y los PDFs generados por `CustomMPdf`?

Laravel genera PDFs en `storage/app/pdf/`. NestJS los sirve con `@fastify/static` en `/pdf/`. Si NestJS también genera PDFs, los escribe en el mismo path. Todo transparente.

---

## Checklist Final Pre-Migración

- [ ] `.env` de producción configurado
- [ ] Dump de BD exportado e importado
- [ ] Archivos de storage copiados (`attachments/` + `pdf/`)
- [ ] `docker compose up -d` — todos los servicios corriendo
- [ ] Smoke test: login funciona
- [ ] Smoke test: archivos se descargan correctamente
- [ ] Smoke test: crear nueva solicitud con archivo adjunto
- [ ] Frontend apunta al dominio correcto
- [ ] Nginx/reverse proxy configurado
- [ ] Backup pre-switch creado
- [ ] Monitoreo de logs activo
- [ ] Equipo notificado del cambio
- [ ] Laravel disponible como fallback

