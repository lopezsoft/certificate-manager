# 📋 Sistema de Notificaciones de Certificados Próximos a Vencer

## 📖 Descripción General

Sistema automatizado de notificaciones para certificados digitales próximos a vencer, implementado usando **Jobs y Scheduled Tasks** de Laravel.

---

## 🏗️ Arquitectura de la Solución

### Componentes Principales

1. **Jobs (Trabajos en Cola)**
   - `SendExpiringCertificatesNotificationsJob`: Notifica a empresas individuales
   - `SendAdminExpiringCertificatesReportJob`: Genera reportes para administradores

2. **Notifications (Notificaciones)**
   - `CertificateExpiringNotification`: Email a empresas con certificados próximos a vencer
   - `AdminExpiringCertificatesReportNotification`: Reporte consolidado para administradores

3. **Scheduled Tasks (Tareas Programadas)**
   - Configuradas en `app/Console/Kernel.php`
   - Ejecutan los Jobs automáticamente según horarios definidos

---

## ⚙️ Configuración

### Variables de Entorno (`.env`)

```env
# Email del administrador que recibirá reportes
CERTIFICATE_ADMIN_EMAIL=gerencia@lopezsoft.net.co

# Días de antelación para notificar (por defecto: 30)
CERTIFICATE_NOTIFICATION_DAYS=30

# Habilitar/deshabilitar funcionalidades
CERTIFICATE_DAILY_NOTIFICATIONS=true
CERTIFICATE_WEEKLY_REPORT=true

# Horarios de ejecución (formato 24h: HH:MM)
CERTIFICATE_NOTIFICATIONS_TIME=08:00
CERTIFICATE_DAILY_REPORT_TIME=07:00
CERTIFICATE_WEEKLY_REPORT_TIME=09:00
CERTIFICATE_WEEKLY_REPORT_DAY=monday

# Colas específicas
CERTIFICATE_QUEUE_NOTIFICATIONS=notifications
CERTIFICATE_QUEUE_REPORTS=reports

# Configuración de reintentos
CERTIFICATE_RETRY_MAX_ATTEMPTS=3

# Logging
CERTIFICATE_LOGGING_ENABLED=true
CERTIFICATE_LOGGING_LEVEL=info
```

### Archivo de Configuración (`config/certificate.php`)

Contiene toda la configuración centralizada del sistema de certificados.

---

## 📅 Tareas Programadas

### 1. Notificaciones a Empresas
- **Frecuencia**: Diaria
- **Horario**: 08:00 AM (Colombia)
- **Job**: `SendExpiringCertificatesNotificationsJob`
- **Función**: Notifica a cada empresa que tiene certificados que vencerán en los próximos 30 días

### 2. Reporte Diario Administrativo
- **Frecuencia**: Diaria
- **Horario**: 07:00 AM (Colombia)
- **Job**: `SendAdminExpiringCertificatesReportJob(false)`
- **Función**: Envía reporte consolidado de todas las empresas al administrador

### 3. Reporte Semanal Administrativo
- **Frecuencia**: Semanal (Lunes)
- **Horario**: 09:00 AM (Colombia)
- **Job**: `SendAdminExpiringCertificatesReportJob(true)`
- **Función**: Reporte semanal detallado con estadísticas completas

### 4. Informes Mensuales a Empresas
- **Frecuencia**: Mensual (Último día del mes)
- **Horario**: 22:00 (10:00 PM) (Colombia)
- **Job**: `SendMonthlyCompanyCertificatesReportJob`
- **Función**: Envía a cada empresa un informe detallado de todos los certificados emitidos durante el mes, segmentados por estado y vigencia

### 5. Informe Mensual Administrativo
- **Frecuencia**: Mensual (Último día del mes)
- **Horario**: 23:00 (11:00 PM) (Colombia)
- **Job**: `SendMonthlyAdminCertificatesReportJob`
- **Función**: Envía al administrador un reporte consolidado de TODOS los certificados emitidos durante el mes, agrupados por empresa, estado y vigencia

---

## 🚀 Funcionamiento

### Flujo de Notificaciones a Empresas

1. El scheduler ejecuta el Job diariamente a las 8:00 AM
2. El Job consulta certificados que vencen en los próximos 30 días
3. Para cada certificado:
   - Verifica que no se haya notificado hoy (usando Cache)
   - Valida que la empresa tenga email configurado
   - Determina el nivel de urgencia (crítico, alto, medio)
   - Envía la notificación
   - Registra el envío en logs
4. Genera un resumen con estadísticas

### Flujo de Reporte Administrativo

1. El scheduler ejecuta el Job diariamente a las 7:00 AM
2. El Job recopila datos de todas las cuentas:
   - Certificados vencidos
   - Certificados críticos (1-7 días)
   - Certificados alta prioridad (8-15 días)
   - Certificados media prioridad (16-30 días)
3. Genera reporte consolidado con:
   - Estadísticas generales
   - Listado detallado por categoría
   - Recomendaciones de acción
4. Envía email al administrador configurado

---

## 📊 Niveles de Urgencia

### 🚨 Crítico (1-7 días)
- **Acción**: Inmediata
- **Email**: Con alerta roja
- **Mensaje**: Requiere renovación urgente

### 🟠 Alta Prioridad (8-15 días)
- **Acción**: Programar contacto urgente
- **Email**: Con alerta naranja
- **Mensaje**: Planificar renovación pronto

### 🟡 Media Prioridad (16-30 días)
- **Acción**: Monitorear y preparar
- **Email**: Con recordatorio amarillo
- **Mensaje**: Planificar renovación oportunamente

### ❌ Vencidos
- **Acción**: Crítica - Contacto inmediato
- **Incluye**: Solo en reportes administrativos

---

## 🔧 Comandos de Gestión

### Ejecutar Manualmente (Comandos Artisan)

Desde v1.8.0 hay comandos Artisan dedicados:

```bash
# Previsualizar qué se notificaría sin enviar emails
php artisan certificates:notify-expiring --dry-run
php artisan certificates:notify-expiring --dry-run --days=15

# Disparar notificaciones a empresas
php artisan certificates:notify-expiring
php artisan certificates:notify-expiring --days=15

# Reporte diario al administrador
php artisan certificates:admin-report

# Reporte semanal al administrador
php artisan certificates:admin-report --weekly

# Reportes mensuales (empresas + admin)
php artisan certificates:monthly-report

# Solo reporte mensual al admin
php artisan certificates:monthly-report --admin-only

# Reporte mensual a una empresa específica
php artisan certificates:monthly-report --company-id=5
```

> **API (endpoint manual para admins):**
> `POST /api/v1/admin/certificates/notify-now`

### Ver Jobs en Cola

```bash
# Ver trabajos pendientes
php artisan queue:work --queue=notifications,reports --tries=3

# Ver trabajos fallidos
php artisan queue:failed

# Reintentar trabajos fallidos
php artisan queue:retry all
```

### Verificar Scheduler

```bash
# Listar tareas programadas
php artisan schedule:list

# Ejecutar scheduler manualmente (simular cron)
php artisan schedule:run

# Ver próxima ejecución
php artisan schedule:list
```

---

## 📝 Logs

### Ubicación de Logs

- **Notificaciones**: `storage/logs/scheduled-certificates-notifications.log`
- **Reportes Admin**: `storage/logs/scheduled-certificates-admin-report.log`
- **Logs generales**: `storage/logs/laravel.log`

### Formato de Logs

Todos los logs incluyen el prefijo `[CertificateExpiration]` para fácil filtrado:

```
[2025-10-21 08:00:01] [CertificateExpiration] Iniciando proceso de notificaciones
[2025-10-21 08:00:05] [CertificateExpiration] Notificación enviada exitosamente
```

### Buscar en Logs

```bash
# Ver logs de certificados
tail -f storage/logs/laravel.log | grep CertificateExpiration

# Ver últimos errores
tail -100 storage/logs/laravel.log | grep "ERROR.*CertificateExpiration"

# Ver estadísticas de ejecución
grep "Proceso completado" storage/logs/scheduled-certificates-notifications.log
```

---

## 🛡️ Seguridad y Buenas Prácticas

### Prevención de Duplicados

- **Cache**: Se usa Cache con TTL de 24 horas
- **Clave**: `cert_notification_{id}_{date}`
- **Resultado**: Cada certificado se notifica máximo 1 vez por día

### Manejo de Errores

1. **Reintentos Automáticos**: 3 intentos con backoff progresivo (60s, 120s, 300s)
2. **Logs Detallados**: Cada error se registra con contexto completo
3. **Notificación Admin**: Si hay múltiples fallos, se notifica al administrador
4. **Queue Fallida**: Los jobs fallidos van a la tabla `failed_jobs`

### Rate Limiting

- **Delay entre envíos**: 0.1 segundos (usleep)
- **Previene**: Saturación del servidor SMTP
- **Queue separadas**: `notifications` y `reports` aisladas

### Overlapping Prevention

- `withoutOverlapping(30)`: Previene ejecuciones simultáneas
- `onOneServer()`: Solo ejecuta en un servidor si hay cluster

---

## 📧 Contenido de los Emails

### Email a Empresas

**Incluye:**
- Saludo personalizado según urgencia
- Nombre de la empresa
- NIT/DNI formateado
- Fecha de vencimiento
- Días restantes
- Mensaje de urgencia contextual
- Botón de acción al sistema
- Datos de contacto

**Diseño:**
- Responsive
- Colores según urgencia
- Íconos descriptivos
- Footer con información legal

### Email Administrativo

**Incluye:**
- Resumen ejecutivo con totales
- Segmentación por urgencia:
  - Vencidos
  - Críticos (1-7 días)
  - Alta prioridad (8-15 días)
  - Media prioridad (16-30 días)
- Detalles de cada certificado:
  - Empresa, NIT, email, teléfono
  - Rep. legal, fecha vencimiento
  - Días restantes
- Estadísticas generales
- Recomendaciones de acción
- Botón de acceso al sistema

---

## 🧪 Testing

### Ejecutar Tests Manuales

```bash
# 1. Verificar configuración
php artisan config:show certificate

# 2. Ejecutar job de notificaciones (test)
php artisan tinker
>>> dispatch(new \App\Jobs\SendExpiringCertificatesNotificationsJob());

# 3. Verificar logs
tail -f storage/logs/laravel.log | grep CertificateExpiration

# 4. Verificar queue
php artisan queue:work --once --queue=notifications
```

### Validar Scheduler

```bash
# Ver próximas ejecuciones
php artisan schedule:list

# Ejecutar todas las tareas programadas (simular cron)
php artisan schedule:run

# Ver output
php artisan schedule:work
```

---

## 🔄 Mantenimiento

### Tareas Regulares

1. **Revisar Logs Semanalmente**
   ```bash
   tail -200 storage/logs/scheduled-certificates-notifications.log
   ```

2. **Monitorear Jobs Fallidos**
   ```bash
   php artisan queue:failed
   ```

3. **Limpiar Jobs Antiguos**
   ```bash
   php artisan queue:flush
   ```

4. **Verificar Cache**
   ```bash
   php artisan cache:clear
   ```

### Actualizar Configuración

1. Modificar `.env`
2. Limpiar cache de configuración:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

---

## 🐛 Troubleshooting

### Problema: No se envían notificaciones

**Posibles Causas:**
1. Cron no configurado en servidor
2. Queue worker no está ejecutándose
3. Configuración SMTP incorrecta
4. Certificados no tienen `expiration_date`

**Solución:**
```bash
# Verificar scheduler
php artisan schedule:list

# Verificar queue
php artisan queue:listen

# Verificar configuración
php artisan config:show mail
php artisan config:show certificate

# Revisar logs
tail -f storage/logs/laravel.log
```

### Problema: Jobs se quedan en cola

**Solución:**
```bash
# Iniciar queue worker
php artisan queue:work --queue=notifications,reports --tries=3 --timeout=300

# O usar supervisor (producción)
sudo supervisorctl restart laravel-worker:*
```

### Problema: Emails duplicados

**Verificación:**
```bash
# Limpiar cache
php artisan cache:clear

# Verificar que no haya múltiples cron configurados
crontab -l
```

---

## 📈 Métricas y Estadísticas

El sistema registra automáticamente:

- Total de certificados procesados
- Notificaciones exitosas
- Notificaciones fallidas
- Notificaciones omitidas (ya enviadas hoy)
- Tiempo de procesamiento
- Empresas sin email
- Distribución por nivel de urgencia

**Ver en logs:**
```bash
grep "Proceso completado" storage/logs/scheduled-certificates-notifications.log
```

---

## 🚀 Despliegue en Producción

### 1. Configurar Cron en Servidor

```bash
# Editar crontab
crontab -e

# Agregar línea (ejecuta cada minuto)
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

### 2. Configurar Queue Worker (Supervisor)

Crear archivo `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /ruta/al/proyecto/artisan queue:work --queue=notifications,reports --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/ruta/al/proyecto/storage/logs/worker.log
stopwaitsecs=3600
```

Iniciar:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### 3. Verificar Configuración de Producción

```bash
# Verificar .env
cat .env | grep CERTIFICATE

# Cachear configuración
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimizar autoload
composer dump-autoload --optimize
```

---

## 📞 Soporte

Para asistencia técnica:
- **Email**: soporte@matias.com.co
- **Logs**: Revisar `storage/logs/`
- **Documentación**: Este archivo

---

## 📜 Changelog

### Versión 1.0.0 (Octubre 2025)
- ✅ Implementación inicial del sistema de notificaciones
- ✅ Jobs para notificaciones a empresas
- ✅ Jobs para reportes administrativos
- ✅ Sistema de caché para prevenir duplicados
- ✅ Segmentación por niveles de urgencia
- ✅ Logging completo y detallado
- ✅ Configuración flexible mediante `.env`
- ✅ Manejo robusto de errores con reintentos

---

**Desarrollado por**: LOPEZSOFT S.A.S.  
**Sistema**: Certificate Manager  
**Fecha**: Octubre 2025
