# Informe: Sistema de Notificaciones de Vencimientos de Certificados

**Fecha:** 2026-02-19
**Versión analizada:** branch `feature/testing-setup`
**Estado general:** Implementación parcial — infraestructura backend completa, faltan comandos Artisan, endpoints API, y un bug en el scheduler mensual.

---

## 1. Resumen Ejecutivo

El sistema tiene una infraestructura de notificaciones **bien diseñada** pero que **no puede activarse ni probarse** sin intervención directa en la base de datos o los jobs, porque:

- No existen comandos Artisan para disparar manualmente los jobs.
- No existen endpoints API que expongan datos de vencimientos al SPA.
- El scheduler mensual tiene un bug que lo hace correr en un día fijo en lugar del último día de cada mes.
- Los jobs **no filtran por `request_status`**: notifican certificados de solicitudes canceladas/rechazadas.

---

## 2. Componentes Implementados

### 2.1 Clases de Notificación (`app/Notifications/`)

| Archivo | Propósito | Canal | Estado |
|---|---|---|---|
| `CertificateExpiringNotification` | Email a empresa — certificado próximo a vencer | Mail (queued) | ✅ Completo |
| `AdminExpiringCertificatesReportNotification` | Reporte consolidado al admin (diario/semanal) | Mail | ✅ Completo |
| `MonthlyCompanyCertificatesReportNotification` | Informe mensual por empresa | Mail | ✅ Completo |
| `MonthlyAdminCertificatesReportNotification` | Informe mensual consolidado admin | Mail | ✅ Completo |

**Niveles de urgencia en `CertificateExpiringNotification`:**
- 🚨 **Critical** (1–7 días): asunto "URGENTE", color error
- ⚠️ **High** (8–15 días): asunto "IMPORTANTE", color warning
- 📌 **Medium** (16–30 días): asunto "Recordatorio", color primary
- 📋 **Low** (31+ días): aviso genérico

### 2.2 Jobs (`app/Jobs/`)

| Archivo | Frecuencia programada | Queue | Estado |
|---|---|---|---|
| `SendExpiringCertificatesNotificationsJob` | Diario 08:00 (Bogotá) | `notifications` | ✅ Completo |
| `SendAdminExpiringCertificatesReportJob` | Diario 07:00 / Lunes 09:00 | `reports` | ✅ Completo |
| `SendMonthlyCompanyCertificatesReportJob` | Último día mes 22:00 | `reports` | ⚠️ Bug (ver §4.1) |
| `SendMonthlyAdminCertificatesReportJob` | Último día mes 23:00 | `reports` | ⚠️ Bug (ver §4.1) |

**Mecanismo anti-duplicados en `SendExpiringCertificatesNotificationsJob`:**
```
Cache key: cert_expiration_notified_{id}_{YYYY-MM-DD}
TTL: 24 horas
```
Si ya se notificó hoy el mismo certificado, se omite sin error.

### 2.3 Scheduler (`app/Console/Kernel.php`)

Cinco entradas configuradas con:
- `withoutOverlapping(30)` — evita ejecuciones simultáneas
- `onOneServer()` — seguro para entornos multi-servidor
- `emailOutputOnFailure(...)` — alerta por email ante fallo
- `appendOutputTo(storage/logs/...)` — logs dedicados por proceso

### 2.4 Configuración (`config/certificate.php`)

Todas las claves son configurables vía `.env`:

```
CERTIFICATE_ADMIN_EMAIL=gerencia@lopezsoft.net.co
CERTIFICATE_NOTIFICATION_DAYS=30
CERTIFICATE_DAILY_NOTIFICATIONS=true
CERTIFICATE_WEEKLY_REPORT=true
CERTIFICATE_QUEUE_NOTIFICATIONS=notifications
CERTIFICATE_QUEUE_REPORTS=reports
CERTIFICATE_RETRY_MAX_ATTEMPTS=3
CERTIFICATE_MONTHLY_REPORTS_ENABLED=true
```

### 2.5 Cola de trabajos

```env
QUEUE_CONNECTION=database
```
Los jobs se despachan a la tabla `jobs` de la DB. **Requiere** que el worker esté corriendo en producción.

---

## 3. Flujo Completo (cuando funciona correctamente)

```
07:00 AM → SendAdminExpiringCertificatesReportJob (diario)
            └─ AdminExpiringCertificatesReportNotification → email admin

08:00 AM → SendExpiringCertificatesNotificationsJob
            ├─ consulta CertificateRequest WHERE expiration_date BETWEEN now AND +30d
            ├─ verifica cache (anti-duplicados)
            ├─ calcula daysRemaining y urgencyLevel
            └─ CertificateExpiringNotification → email empresa

09:00 AM (lunes) → SendAdminExpiringCertificatesReportJob (semanal)
                    └─ AdminExpiringCertificatesReportNotification → email admin

Último día del mes, 22:00 → SendMonthlyCompanyCertificatesReportJob
                              └─ MonthlyCompanyCertificatesReportNotification → email empresa

Último día del mes, 23:00 → SendMonthlyAdminCertificatesReportJob
                              └─ MonthlyAdminCertificatesReportNotification → email admin
```

---

## 4. Problemas y Gaps Identificados

### 4.1 🔴 Bug: Scheduler mensual con día fijo

**Archivo:** [app/Console/Kernel.php](../app/Console/Kernel.php) — líneas 97 y 114

```php
// CÓDIGO ACTUAL (BUG)
->monthlyOn(Carbon::now()->endOfMonth()->day, '22:00')
```

`Carbon::now()->endOfMonth()->day` se evalúa **una sola vez al arrancar el proceso**, no en cada ejecución. En un proceso que arranca en enero (día 31), buscará el día 31 de cada mes — en febrero nunca se ejecutará.

**Corrección requerida:**
```php
// CORRECTO: usar lastDayOfMonth()
->lastDayOfMonth('22:00')
```

### 4.2 🔴 Jobs no filtran por `request_status`

**Archivo:** [app/Jobs/SendExpiringCertificatesNotificationsJob.php](../app/Jobs/SendExpiringCertificatesNotificationsJob.php) — líneas 167–176

El query de `getExpiringCertificates()` no filtra por estado de la solicitud. Se enviarán notificaciones para certificados de solicitudes en estado `cancelled`, `rejected` o `pending` (que no tienen certificado emitido aún).

**Corrección requerida:** Agregar filtro en la query:
```php
->whereIn('request_status', ['issued', 'delivered'])
```

### 4.3 🟠 No existen comandos Artisan para pruebas/trigger manual

**Directorio:** [app/Console/Commands/](../app/Console/Commands/) — solo tiene `TestAwsTextract`, `WebhookCleanupCommand`, `WebhookRetryCommand`.

Sin comandos Artisan es imposible:
- Probar el sistema en desarrollo sin esperar la hora programada.
- Disparar notificaciones manualmente (ej. ante urgencia).
- Validar la configuración de email.

**Comandos necesarios:**
```
php artisan certificates:notify-expiring [--dry-run] [--days=30]
php artisan certificates:admin-report [--weekly]
php artisan certificates:monthly-report [--company-id=] [--admin-only]
```

### 4.4 🟠 No existen endpoints API para el SPA

**Archivo:** [routes/api.php](../routes/api.php) — no hay rutas de notificaciones ni vencimientos.

El SPA no puede:
- Consultar la lista de certificados próximos a vencer.
- Mostrar un badge/contador de alertas.
- Listar notificaciones pendientes de lectura (tabla `notifications` de Laravel).

**Endpoints necesarios:**
```
GET  /api/certificates/expiring          — lista vencimientos próximos (filtrable por días)
GET  /api/notifications                  — notificaciones del usuario autenticado (DB)
POST /api/notifications/{id}/read        — marcar como leída
POST /api/notifications/read-all         — marcar todas como leídas
POST /api/admin/certificates/notify-now  — disparar notificación manual (solo admin)
```

### 4.5 🟡 `CertificateExpiringNotification` usa solo canal `mail`

**Archivo:** [app/Notifications/CertificateExpiringNotification.php](../app/Notifications/CertificateExpiringNotification.php) — línea 52

```php
public function via(object $notifiable): array
{
    return ['mail'];
}
```

El método `toArray()` está implementado (líneas 99–109), lo que indica que se pensó en el canal `database` para persistir notificaciones en la tabla `notifications`, pero no está activado.

Si el SPA necesita mostrar notificaciones in-app, hay que agregar `'database'` al array de canales.

### 4.6 🟡 No hay worker corriendo en el servidor

La configuración `QUEUE_CONNECTION=database` requiere un proceso permanente:
```bash
php artisan queue:work --queue=notifications,reports --tries=3
```
Sin este proceso, los jobs se encolan en la tabla `jobs` pero **nunca se ejecutan**. Tampoco se ejecuta el scheduler sin:
```bash
php artisan schedule:run   # cada minuto (via cron)
```

---

## 5. Requisitos Previos para que Funcione

| Requisito | Estado | Acción necesaria |
|---|---|---|
| SMTP configurado | ✅ (smtp.gmail.com) | Verificar contraseña de app |
| `CERTIFICATE_ADMIN_EMAIL` en .env | ✅ | — |
| Worker de cola corriendo | ❓ | Iniciar `queue:work` |
| Cron ejecutando `schedule:run` | ❓ | Configurar en servidor |
| Tabla `jobs` existe (migraciones) | ❓ | Verificar con usuario |
| Tabla `notifications` existe | ✅ (migration 2023_05_13) | — |

---

## 6. Trabajo Pendiente — Prioridad Recomendada

### Prioridad Alta (bugs que rompen funcionalidad)

1. **Fix bug scheduler mensual** — `lastDayOfMonth()` en Kernel.php
2. **Filtrar por `request_status`** en `getExpiringCertificates()`

### Prioridad Media (funcionalidad crítica faltante)

3. **Crear comandos Artisan** para trigger manual y pruebas
4. **Activar canal `database`** en `CertificateExpiringNotification.toArray()`
5. **Crear endpoints API** para el SPA (vencimientos + notificaciones)

### Prioridad Baja (mejoras)

6. **Documentar setup del worker** y cron en el servidor
7. **Tests unitarios** para los Jobs y Notifications

---

## 7. Archivos Clave del Sistema

```
backend/
├── app/
│   ├── Console/
│   │   └── Kernel.php                                    ← Scheduling (bug §4.1)
│   ├── Jobs/
│   │   ├── SendExpiringCertificatesNotificationsJob.php  ← Job principal (bug §4.2)
│   │   ├── SendAdminExpiringCertificatesReportJob.php
│   │   ├── SendMonthlyCompanyCertificatesReportJob.php
│   │   └── SendMonthlyAdminCertificatesReportJob.php
│   └── Notifications/
│       ├── CertificateExpiringNotification.php           ← (canal DB faltante §4.5)
│       ├── AdminExpiringCertificatesReportNotification.php
│       ├── MonthlyCompanyCertificatesReportNotification.php
│       └── MonthlyAdminCertificatesReportNotification.php
├── config/
│   └── certificate.php                                   ← Config centralizada
└── routes/
    └── api.php                                           ← Faltan endpoints §4.4
```

---

*Informe generado el 2026-02-19. Pendiente de revisión y aprobación antes de implementar correcciones.*
