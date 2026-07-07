# Guía de Migraciones en Producción — Certificate Manager
> **Backend:** Laravel 10 | **Fecha:** 2026-07-06

---

## Cómo funciona en este proyecto

Este proyecto **NO usa `php artisan migrate` global** para las migraciones de negocio.
Existen dos comandos wrapper seguros, uno por módulo, que solo pueden tocar su propia subcarpeta:

| Comando | Subcarpeta controlada |
|---|---|
| `php artisan certificates:migrate` | `database/migrations/certificates/` |
| `php artisan viafirma:migrate` | `database/migrations/viafirma/` |

**Protecciones que tienen ambos wrappers:**
- Por defecto corren en **modo dry-run** (`--pretend`). No cambian nada sin `--apply`.
- Fuera del entorno `local` exigen `--force` para evitar accidentes.
- Solo aceptan archivos de su subcarpeta — rechazan cualquier otra ruta.

---

## Flujo completo para producción

### 1. Backup primero

```bash
mysqldump -u [usuario] -p[password] [nombre_bd] > backup_$(date +%Y%m%d_%H%M%S).sql
```

---

### 2. Ver qué migraciones ya corrieron y cuáles están pendientes

```bash
# Módulo Viafirma
php artisan viafirma:migrate:status

# Módulo Certificates (usa el status estándar filtrado por path)
php artisan migrate:status --path=/database/migrations/certificates
```

---

### 3. Dry-run: ver el SQL sin aplicar nada

Sin `--apply`, ambos comandos solo muestran el SQL. Úsalo siempre primero:

```bash
# Certificates
php artisan certificates:migrate 2026_06_20_000001_add_issuance_dates_to_certificate_requests.php

# Viafirma
php artisan viafirma:migrate 2026_06_10_120000_add_revocation_fields_to_viafirma_certificate_requests.php
```

Si la salida del SQL es la esperada, continúa al paso siguiente.

---

### 4. Activar modo mantenimiento

```bash
php artisan down --message="Actualizando sistema, volvemos en breve." --retry=60
```

---

### 5. Aplicar cada migración pendiente, una por una

Agrega `--apply --force` para ejecutar en producción:

**Módulo `certificates/`** — migraciones pendientes:

```bash
php artisan certificates:migrate 2026_06_20_000001_add_issuance_dates_to_certificate_requests.php --apply --force

php artisan certificates:migrate 2026_06_20_000002_add_revoked_expired_to_request_status_enum.php --apply --force

php artisan certificates:migrate 2026_06_20_010254_add_renewal_fields_to_certificate_orders.php --apply --force
```

**Módulo `viafirma/`** — migraciones pendientes:

```bash
php artisan viafirma:migrate 2026_05_15_120001_create_viafirma_certificate_requests_table.php --apply --force

php artisan viafirma:migrate 2026_05_15_120002_create_viafirma_status_history_table.php --apply --force

php artisan viafirma:migrate 2026_06_10_120000_add_revocation_fields_to_viafirma_certificate_requests.php --apply --force

php artisan viafirma:migrate 2026_06_18_220000_add_auto_redownload_attempts_to_viafirma_certificate_requests.php --apply --force

php artisan viafirma:migrate 2026_06_18_230000_normalize_viafirma_certificate_requests.php --apply --force
```

> Verifica la salida de cada comando antes de ejecutar el siguiente.

---

### 6. Limpiar cachés

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

### 7. Quitar modo mantenimiento

```bash
php artisan up
```

---

### 8. Verificar estado final

```bash
php artisan viafirma:migrate:status
php artisan migrate:status --path=/database/migrations/certificates
```

---

## Rollback puntual de emergencia

Si una migración falla, puedes revertirla individualmente:

```bash
# Certificates
php artisan certificates:migrate [nombre_archivo.php] --rollback --apply --force

# Viafirma
php artisan viafirma:migrate [nombre_archivo.php] --rollback --apply --force
```

Si el rollback tampoco resuelve, restaura el backup:

```bash
mysql -u [usuario] -p[password] [nombre_bd] < backup_YYYYMMDD_HHMMSS.sql
```

---

## Notas importantes

- Tras cada migración aplicada, registra la ejecución en `CHANGELOG.md` con fecha, entorno y autor (política §10.bis del roadmap).
- El `.env` en producción debe tener `APP_ENV=production` y `APP_DEBUG=false`.
- Las migraciones de la raíz `database/migrations/` (tablas base, jobs, sessions, etc.) se aplican con `php artisan migrate --force` solo si hay pendientes nuevas ahí — verifica con `migrate:status` sin `--path`.
