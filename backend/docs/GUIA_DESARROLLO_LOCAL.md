# Guía de Desarrollo Local — Certificate Manager

**Fecha:** 2026-05-05  
**Versión:** 1.0  
**Requisitos:** PHP 8.1+, Composer, MySQL 8+, WAMP/XAMPP

---

## 1. Configuración Inicial

```bash
# Clonar e instalar dependencias
git clone https://github.com/lopezsoft/certificate-manager.git
cd certificate-manager/backend
composer install

# Configurar entorno
cp .env.example .env
php artisan key:generate
```

Editar `.env` con las credenciales de la base de datos local.

---

## 2. Emulación de Cronjobs (Tareas Programadas)

El proyecto utiliza el **Task Scheduling** de Laravel para ejecutar Jobs programados (notificaciones, reportes mensuales, expiración de cupos). En producción, un cron del servidor invoca `schedule:run` cada minuto.

### En desarrollo local, usar `schedule:work`:

```bash
php artisan schedule:work
```

Este comando simula el cron cada 60 segundos, ejecutando los Jobs programados en el `Kernel.php` cuando corresponda.

### Comandos registrados en el Schedule:

| Comando / Job | Frecuencia | Descripción |
|---------------|-----------|-------------|
| `SendExpiringCertificatesNotificationsJob` | Diario 8:00 AM | Notifica empresas con certificados por vencer |
| `SendAdminExpiringCertificatesReportJob` | Diario 7:00 AM | Reporte consolidado al admin |
| `SendAdminExpiringCertificatesReportJob` (semanal) | Lunes 9:00 AM | Reporte semanal detallado |
| `SendMonthlyCompanyCertificatesReportJob` | Último día del mes 10:00 PM | Informe mensual por empresa |
| `SendMonthlyAdminCertificatesReportJob` | Último día del mes 11:00 PM | Informe mensual consolidado |
| `quotas:expire` | Diario 00:05 AM | Expira cupos POSTPAID vencidos |

### Verificar los comandos registrados:

```bash
php artisan schedule:list
```

---

## 3. Ejecución de Tests

```bash
# Ejecutar suite completa
php artisan test

# Ejecutar un test específico
php artisan test --filter=NombreDelTest

# Ejecutar con coverage (requiere Xdebug)
php artisan test --coverage
```

> **Importante:** Los tests NO tocan la base de datos real. Usan Mocks/Fakes para las APIs externas (Wompi, Google Vision, Gemini).

---

## 4. Variables de Entorno para CORS (Local)

En `.env` agregar:

```env
CORS_ALLOWED_ORIGINS=http://localhost:4200,http://localhost:8100
```

En producción, esta variable debe contener los dominios reales de la SPA y clientes autorizados.

---

## 5. Reglas de Seguridad para BD

> ⛔ **NUNCA ejecutar migraciones ni seeders masivos.**

```bash
# ✅ Correcto: Migración individual
php artisan migrate --path=database/migrations/2026_XX_XX_NOMBRE.php

# ✅ Correcto: Seeder individual
php artisan db:seed --class=NombreDelSeeder

# ❌ PROHIBIDO
php artisan migrate
php artisan db:seed
php artisan migrate:fresh
php artisan migrate:refresh
```

Consultar el [Roadmap SCRUM](./2026-05-04_ROADMAP_IMPLEMENTACION_SCRUM.md) para más detalles.
