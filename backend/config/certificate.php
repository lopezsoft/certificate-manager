<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Certificate Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración específica para el sistema de gestión de certificados
    | digitales, incluyendo notificaciones automáticas y reportes.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Admin Email
    |--------------------------------------------------------------------------
    |
    | Correo electrónico del administrador que recibirá los reportes
    | consolidados de certificados próximos a vencer.
    |
    */
    'admin_email' => env('CERTIFICATE_ADMIN_EMAIL', env('MAIL_SUPPORT_ADDRESS', 'soporte@matias.com.co')),

    /*
    |--------------------------------------------------------------------------
    | Notification Days
    |--------------------------------------------------------------------------
    |
    | Número de días de antelación para enviar notificaciones de vencimiento.
    | Por defecto: 30 días
    |
    */
    'notification_days' => env('CERTIFICATE_NOTIFICATION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Expired Report Max Age
    |--------------------------------------------------------------------------
    |
    | Antigüedad máxima (días) de un certificado vencido para seguir apareciendo
    | en el reporte administrativo. Más allá de este umbral es ruido: el operador
    | ya tuvo tiempo de gestionarlo o el cliente ya renovó/se fue.
    | Por defecto: 30 días
    |
    */
    'expired_report_max_age_days' => env('CERTIFICATE_EXPIRED_REPORT_MAX_AGE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Daily Notifications
    |--------------------------------------------------------------------------
    |
    | Habilitar o deshabilitar las notificaciones diarias automáticas.
    |
    */
    'daily_notifications_enabled' => env('CERTIFICATE_DAILY_NOTIFICATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Weekly Report
    |--------------------------------------------------------------------------
    |
    | Habilitar o deshabilitar el reporte semanal consolidado.
    |
    */
    'weekly_report_enabled' => env('CERTIFICATE_WEEKLY_REPORT', true),

    /*
    |--------------------------------------------------------------------------
    | Notification Schedule
    |--------------------------------------------------------------------------
    |
    | Configuración de horarios para las notificaciones y reportes.
    |
    */
    'schedule' => [
        'notifications_time' => env('CERTIFICATE_NOTIFICATIONS_TIME', '08:00'),
        'daily_report_time' => env('CERTIFICATE_DAILY_REPORT_TIME', '07:00'),
        'weekly_report_day' => env('CERTIFICATE_WEEKLY_REPORT_DAY', 'monday'),
        'weekly_report_time' => env('CERTIFICATE_WEEKLY_REPORT_TIME', '09:00'),
        'timezone' => env('APP_TIMEZONE', 'America/Bogota'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Urgency Levels
    |--------------------------------------------------------------------------
    |
    | Definición de los niveles de urgencia según los días restantes.
    |
    */
    'urgency_levels' => [
        'critical' => 7,   // 1-7 días
        'high' => 15,      // 8-15 días
        'medium' => 30,    // 16-30 días
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de las colas para procesar notificaciones y reportes.
    |
    */
    'queues' => [
        'notifications' => env('CERTIFICATE_QUEUE_NOTIFICATIONS', 'notifications'),
        'reports' => env('CERTIFICATE_QUEUE_REPORTS', 'reports'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de reintentos para jobs fallidos.
    |
    */
    'retry' => [
        'max_attempts' => env('CERTIFICATE_RETRY_MAX_ATTEMPTS', 3),
        'backoff' => [60, 120, 300], // En segundos
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de caché para evitar envíos duplicados.
    |
    */
    'cache' => [
        'notification_ttl' => 86400, // 24 horas en segundos
        'prefix' => 'cert_notification_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuración de logging para el sistema de certificados.
    |
    */
    'logging' => [
        'enabled' => env('CERTIFICATE_LOGGING_ENABLED', true),
        'level' => env('CERTIFICATE_LOGGING_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monthly Reports
    |--------------------------------------------------------------------------
    |
    | Configuración para los informes mensuales de certificados emitidos.
    |
    */
    'monthly_reports' => [
        'enabled' => env('CERTIFICATE_MONTHLY_REPORTS_ENABLED', true),
        'company_reports_enabled' => env('CERTIFICATE_MONTHLY_COMPANY_REPORTS_ENABLED', true),
        'admin_report_enabled' => env('CERTIFICATE_MONTHLY_ADMIN_REPORT_ENABLED', true),
        'company_reports_time' => env('CERTIFICATE_MONTHLY_COMPANY_REPORTS_TIME', '22:00'),
        'admin_report_time' => env('CERTIFICATE_MONTHLY_ADMIN_REPORT_TIME', '23:00'),
        'send_on_last_day' => env('CERTIFICATE_MONTHLY_REPORTS_LAST_DAY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de límites para la carga de archivos adjuntos en las
    | solicitudes de certificados.
    |
    */
    'file_upload' => [
        // Tamaño máximo por archivo individual en MB
        'max_file_size' => env('CERTIFICATE_MAX_FILE_SIZE', 7),

        // Tamaño máximo total de todos los archivos en MB
        'max_total_size' => env('CERTIFICATE_MAX_TOTAL_SIZE', 10),

        // Número máximo de archivos permitidos
        'max_files' => env('CERTIFICATE_MAX_FILES', 3),

        // Número mínimo de archivos requeridos
        'min_files' => env('CERTIFICATE_MIN_FILES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Issuance Providers (provider-agnostic)
    |--------------------------------------------------------------------------
    |
    | Configuración del subsistema agnóstico de emisión de certificados.
    | El sistema soporta múltiples proveedores que implementan el contrato
    | App\Contracts\CertificateIssuanceProvider. La elección se resuelve en
    | runtime por App\Services\Certificate\CertificateIssuanceProviderFactory.
    |
    | Ver: docs/2026-05-19-15-00-PLAN-UNIFICACION-API-V1-Y-PROVEEDOR-AGNOSTICO-VIAFIRMA.md
    |
    */
    'issuance' => [
        // Proveedor por defecto cuando no hay override por payload ni por empresa.
        // Valores soportados de fábrica: 'mail', 'viafirma'.
        'default_provider' => env('CERTIFICATE_ISSUANCE_PROVIDER', 'mail'),

        // Lista blanca de proveedores resolubles por el factory.
        'providers' => [
            'mail'     => \App\Services\Certificate\Providers\MailIssuanceProvider::class,
            'viafirma' => \App\Services\Certificate\Providers\ViafirmaIssuanceProvider::class,
        ],

        // Permitir que un caller con rol admin fuerce un proveedor por payload.
        'allow_payload_override' => env('CERTIFICATE_ISSUANCE_ALLOW_OVERRIDE', false),

        // Canal de log dedicado (vacío = canal por defecto del stack).
        'log_channel' => env('CERTIFICATE_ISSUANCE_LOG_CHANNEL', ''),

    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Configuration (Certificate-specific)
    |--------------------------------------------------------------------------
    |
    | Direcciones de correo utilizadas por el sistema de certificados.
    | Centraliza los emails que antes estaban hardcodeados en handlers y services.
    |
    */
    'mail' => [
        // Email de soporte que se muestra en el Excel y recibe notificaciones
        'support_address' => env('MAIL_SUPPORT_ADDRESS', 'soporte@matias.com.co'),

        // Email destino para el envío de solicitudes (legacy mail flow)
        'receipt_email' => env('RECEIPT_EMAIL', 'soporte@matias.com.co'),

        // Enviar copia a gerencia cuando se envía una solicitud
        'send_to_support' => env('SEND_MAIL_TO_SUPPORT', false),
     ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration (Certificate Artifacts)
    |--------------------------------------------------------------------------
    |
    | Almacenamiento de artefactos de certificados (agnóstico de proveedor).
    | El almacenamiento es una responsabilidad transversal que NO pertenece a
    | un proveedor concreto. Conviven Viafirma y otros proveedores.
    |
    | Estructura de rutas:
    |   {disk}://{prefix}/certificates/{provider}/{artifact}/{filename}
    |
    | El path por proveedor/artefacto se resuelve en CertificateStoragePathResolver.
    | `prefix` es un nombre libre por entorno (no APP_ENV) para evitar colisiones.
    |
    */
    'storage' => [
        'disk'   => env('CERT_STORAGE_DISK', env('VIAFIRMA_DISK', 'local')),
        'prefix' => env('CERT_STORAGE_PREFIX', 'local'),

        // Ruta principal para almacenar archivos de solicitudes de certificados.
        // Configurable por entorno para separar el guardado de archivos.
        // Ej: AWS_MAIN_PATH=companies-prod, AWS_MAIN_PATH=companies-staging
        'main_path' => env('AWS_MAIN_PATH', 'companies'),

        // Disco del proveedor LEGACY (otro proveedor). Históricamente 'attachment'.
        // Al migrar a S3 se cambia a 's3' y, como los archivos se copian a la MISMA
        // ruta relativa, las lecturas siguen funcionando sin reescribir rutas en BD.
        'legacy_disk' => env('CERT_LEGACY_DISK', 'attachment'),

        // Sub-rutas por proveedor/artefacto, relativas a {prefix}/certificates/.
        'paths' => [
            'viafirma_p12' => env('VIAFIRMA_P12_PATH', 'viafirma/p12'),
            'viafirma_p7b' => env('VIAFIRMA_P7B_PATH', 'viafirma/p7b'),
        ],
    ],

 ];
