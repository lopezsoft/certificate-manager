<?php

/**
 * Script de Testing para Sistema de Notificaciones de Certificados
 * 
 * Este script permite probar manualmente el sistema de notificaciones
 * sin esperar a que se ejecuten las tareas programadas.
 * 
 * USO:
 * php artisan tinker < tests/TestCertificateNotifications.php
 * 
 * O copiar y pegar en tinker:
 * php artisan tinker
 * 
 */

// ============================================================================
// TEST 1: Ejecutar Job de Notificaciones a Empresas
// ============================================================================

echo "\n========================================\n";
echo "TEST 1: Notificaciones a Empresas\n";
echo "========================================\n\n";

try {
    $job = new \App\Jobs\SendExpiringCertificatesNotificationsJob();
    dispatch($job);
    echo "✅ Job despachado exitosamente\n";
    echo "📊 Revise los logs en: storage/logs/laravel.log\n";
    echo "🔍 Filtro: grep CertificateExpiration storage/logs/laravel.log\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// TEST 2: Ejecutar Job de Reporte Diario Administrativo
// ============================================================================

echo "========================================\n";
echo "TEST 2: Reporte Diario Administrativo\n";
echo "========================================\n\n";

try {
    $job = new \App\Jobs\SendAdminExpiringCertificatesReportJob(false);
    dispatch($job);
    echo "✅ Job de reporte diario despachado exitosamente\n";
    echo "📧 Email será enviado a: " . config('certificate.admin_email') . "\n";
    echo "📊 Revise los logs en: storage/logs/scheduled-certificates-admin-report.log\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// TEST 3: Ejecutar Job de Reporte Semanal Administrativo
// ============================================================================

echo "========================================\n";
echo "TEST 3: Reporte Semanal Administrativo\n";
echo "========================================\n\n";

try {
    $job = new \App\Jobs\SendAdminExpiringCertificatesReportJob(true);
    dispatch($job);
    echo "✅ Job de reporte semanal despachado exitosamente\n";
    echo "📧 Email será enviado a: " . config('certificate.admin_email') . "\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// TEST 4: Verificar Certificados Próximos a Vencer
// ============================================================================

echo "========================================\n";
echo "TEST 4: Consulta de Certificados\n";
echo "========================================\n\n";

try {
    $notificationDays = config('certificate.notification_days', 30);
    $expirationThreshold = now()->addDays($notificationDays);
    
    $certificates = \App\Models\CertificateRequest::with(['company'])
        ->whereNotNull('expiration_date')
        ->where('expiration_date', '>', now())
        ->where('expiration_date', '<=', $expirationThreshold)
        ->whereHas('company', function ($query) {
            $query->whereNotNull('email')->where('email', '!=', '');
        })
        ->orderBy('expiration_date', 'asc')
        ->get();
    
    echo "📊 Total de certificados próximos a vencer: " . $certificates->count() . "\n\n";
    
    if ($certificates->count() > 0) {
        echo "Primeros 5 certificados:\n";
        echo "------------------------\n";
        
        foreach ($certificates->take(5) as $cert) {
            $daysRemaining = now()->diffInDays(\Carbon\Carbon::parse($cert->expiration_date), false);
            echo "- {$cert->company_name} (NIT: {$cert->dni})\n";
            echo "  Vence: {$cert->expiration_date} ({$daysRemaining} días)\n";
            echo "  Email: " . ($cert->company->email ?? 'Sin email') . "\n\n";
        }
    } else {
        echo "✅ No hay certificados próximos a vencer en los próximos {$notificationDays} días\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// TEST 5: Verificar Configuración
// ============================================================================

echo "========================================\n";
echo "TEST 5: Configuración del Sistema\n";
echo "========================================\n\n";

echo "Email Administrador: " . config('certificate.admin_email') . "\n";
echo "Días de Notificación: " . config('certificate.notification_days') . "\n";
echo "Notificaciones Diarias: " . (config('certificate.daily_notifications_enabled') ? 'Activadas' : 'Desactivadas') . "\n";
echo "Reporte Semanal: " . (config('certificate.weekly_report_enabled') ? 'Activado' : 'Desactivado') . "\n";
echo "Horario Notificaciones: " . config('certificate.schedule.notifications_time') . "\n";
echo "Horario Reporte Diario: " . config('certificate.schedule.daily_report_time') . "\n";
echo "Horario Reporte Semanal: " . config('certificate.schedule.weekly_report_time') . "\n\n";

// ============================================================================
// TEST 6: Verificar Queue
// ============================================================================

echo "========================================\n";
echo "TEST 6: Estado de la Cola\n";
echo "========================================\n\n";

try {
    $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
    $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
    
    echo "📥 Jobs pendientes: {$pendingJobs}\n";
    echo "❌ Jobs fallidos: {$failedJobs}\n\n";
    
    if ($pendingJobs > 0) {
        echo "💡 Para procesar los jobs, ejecute:\n";
        echo "   php artisan queue:work --queue=notifications,reports --tries=3\n\n";
    }
    
    if ($failedJobs > 0) {
        echo "⚠️  Hay jobs fallidos. Para ver detalles:\n";
        echo "   php artisan queue:failed\n";
        echo "   Para reintentar: php artisan queue:retry all\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// TEST 7: Verificar Tareas Programadas
// ============================================================================

echo "========================================\n";
echo "TEST 7: Tareas Programadas\n";
echo "========================================\n\n";

echo "Para ver las tareas programadas, ejecute:\n";
echo "  php artisan schedule:list\n\n";

echo "Para ejecutar el scheduler manualmente:\n";
echo "  php artisan schedule:run\n\n";

echo "Para mantener el scheduler activo (development):\n";
echo "  php artisan schedule:work\n\n";

// ============================================================================
// INSTRUCCIONES FINALES
// ============================================================================

echo "========================================\n";
echo "INSTRUCCIONES FINALES\n";
echo "========================================\n\n";

echo "1. Verificar que el Queue Worker esté ejecutándose:\n";
echo "   php artisan queue:work --queue=notifications,reports\n\n";

echo "2. Para testing inmediato, puede ejecutar manualmente:\n";
echo "   php artisan schedule:run\n\n";

echo "3. Ver logs en tiempo real:\n";
echo "   tail -f storage/logs/laravel.log | grep CertificateExpiration\n\n";

echo "4. En producción, configurar cron:\n";
echo "   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1\n\n";

echo "========================================\n";
echo "Testing completado\n";
echo "========================================\n\n";
