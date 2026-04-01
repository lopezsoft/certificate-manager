<?php

/**
 * Script de Testing para Informes Mensuales de Certificados
 * 
 * Este script permite probar manualmente los informes mensuales
 * sin esperar al último día del mes.
 * 
 * USO:
 * php artisan tinker
 * 
 * Luego copiar y pegar los comandos necesarios
 */

echo "\n========================================\n";
echo "TESTING: Informes Mensuales de Certificados\n";
echo "========================================\n\n";

// ============================================================================
// TEST 1: Informe Mensual para UNA Empresa Específica
// ============================================================================

echo "========================================\n";
echo "TEST 1: Informe Mensual - Empresa Específica\n";
echo "========================================\n\n";

try {
    // Definir el periodo (mes anterior por defecto)
    $startDate = \Carbon\Carbon::now()->subMonth()->startOfMonth();
    $endDate = \Carbon\Carbon::now()->subMonth()->endOfMonth();
    
    echo "Periodo: {$startDate->format('d/m/Y')} - {$endDate->format('d/m/Y')}\n\n";
    
    // Obtener primera empresa con certificados
    $company = \App\Models\Company::whereHas('certificateRequests', function($q) use ($startDate, $endDate) {
        $q->whereBetween('created_at', [$startDate, $endDate]);
    })->first();
    
    if ($company) {
        echo "✅ Empresa encontrada: {$company->company_name}\n";
        echo "   Email: {$company->email}\n";
        echo "   ID: {$company->id}\n\n";
        
        // Despachar job para esta empresa
        $job = new \App\Jobs\SendMonthlyCompanyCertificatesReportJob(
            $company->id,
            $startDate,
            $endDate
        );
        dispatch($job);
        
        echo "✅ Job despachado exitosamente\n";
        echo "📧 El informe será enviado a: {$company->email}\n\n";
    } else {
        echo "⚠️ No se encontraron empresas con certificados en el periodo\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// TEST 2: Informes Mensuales para TODAS las Empresas
// ============================================================================

echo "========================================\n";
echo "TEST 2: Informes Mensuales - Todas las Empresas\n";
echo "========================================\n\n";

try {
    $startDate = \Carbon\Carbon::now()->subMonth()->startOfMonth();
    $endDate = \Carbon\Carbon::now()->subMonth()->endOfMonth();
    
    echo "Periodo: {$startDate->format('d/m/Y')} - {$endDate->format('d/m/Y')}\n\n";
    
    // Despachar job para todas las empresas
    $job = new \App\Jobs\SendMonthlyCompanyCertificatesReportJob(
        null, // null = todas las empresas
        $startDate,
        $endDate
    );
    dispatch($job);
    
    echo "✅ Job despachado exitosamente para TODAS las empresas\n";
    echo "📊 Se procesarán todas las empresas con certificados en el periodo\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// TEST 3: Informe Mensual Administrativo Consolidado
// ============================================================================

echo "========================================\n";
echo "TEST 3: Informe Mensual Administrativo\n";
echo "========================================\n\n";

try {
    $startDate = \Carbon\Carbon::now()->subMonth()->startOfMonth();
    $endDate = \Carbon\Carbon::now()->subMonth()->endOfMonth();
    
    echo "Periodo: {$startDate->format('d/m/Y')} - {$endDate->format('d/m/Y')}\n\n";
    
    // Despachar job de reporte admin
    $job = new \App\Jobs\SendMonthlyAdminCertificatesReportJob($startDate, $endDate);
    dispatch($job);
    
    echo "✅ Job de informe administrativo despachado exitosamente\n";
    echo "📧 Email será enviado a: " . config('certificate.admin_email') . "\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// TEST 4: Consultar Certificados del Mes
// ============================================================================

echo "========================================\n";
echo "TEST 4: Estadísticas del Mes\n";
echo "========================================\n\n";

try {
    $startDate = \Carbon\Carbon::now()->subMonth()->startOfMonth();
    $endDate = \Carbon\Carbon::now()->subMonth()->endOfMonth();
    
    $certificates = \App\Models\CertificateRequest::whereBetween('created_at', [$startDate, $endDate])
        ->with('company')
        ->get();
    
    echo "📊 Total de certificados emitidos: " . $certificates->count() . "\n";
    
    if ($certificates->count() > 0) {
        $byCompany = $certificates->groupBy('company_id');
        echo "🏢 Total de empresas: " . $byCompany->count() . "\n";
        echo "📈 Promedio por empresa: " . round($certificates->count() / $byCompany->count(), 1) . "\n\n";
        
        // Top 5 empresas
        $topCompanies = $byCompany->sortByDesc(function($certs) {
            return $certs->count();
        })->take(5);
        
        echo "🏆 Top 5 Empresas:\n";
        $position = 1;
        foreach ($topCompanies as $companyId => $companyCerts) {
            $company = $companyCerts->first()->company;
            echo "  {$position}. {$company->company_name} - {$companyCerts->count()} certificados\n";
            $position++;
        }
        echo "\n";
        
        // Por vigencia
        $now = \Carbon\Carbon::now();
        $active = 0;
        $expired = 0;
        $expiring = 0;
        $pending = 0;
        
        foreach ($certificates as $cert) {
            if (!$cert->expiration_date) {
                $pending++;
                continue;
            }
            
            $expDate = \Carbon\Carbon::parse($cert->expiration_date);
            if ($expDate < $now) {
                $expired++;
            } elseif ($expDate <= $now->copy()->addDays(30)) {
                $expiring++;
            } else {
                $active++;
            }
        }
        
        echo "📊 Por Vigencia:\n";
        echo "  ✅ Activos: {$active}\n";
        echo "  ⚠️ Próximos a vencer: {$expiring}\n";
        echo "  ❌ Vencidos: {$expired}\n";
        echo "  ⏳ Pendientes: {$pending}\n\n";
        
        // Por estado
        $byStatus = $certificates->groupBy('request_status');
        echo "📋 Por Estado:\n";
        foreach ($byStatus as $status => $statusCerts) {
            echo "  • {$status}: {$statusCerts->count()}\n";
        }
        echo "\n";
    } else {
        echo "⚠️ No hay certificados emitidos en el periodo\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// TEST 5: Verificar Configuración
// ============================================================================

echo "========================================\n";
echo "TEST 5: Configuración de Informes Mensuales\n";
echo "========================================\n\n";

echo "Informes Mensuales Habilitados: " . (config('certificate.monthly_reports.enabled') ? 'Sí' : 'No') . "\n";
echo "Informes a Empresas: " . (config('certificate.monthly_reports.company_reports_enabled') ? 'Sí' : 'No') . "\n";
echo "Informe Admin: " . (config('certificate.monthly_reports.admin_report_enabled') ? 'Sí' : 'No') . "\n";
echo "Horario Informes Empresas: " . config('certificate.monthly_reports.company_reports_time') . "\n";
echo "Horario Informe Admin: " . config('certificate.monthly_reports.admin_report_time') . "\n";
echo "Email Administrador: " . config('certificate.admin_email') . "\n\n";

// ============================================================================
// TEST 6: Simular Informe del Mes Actual (hasta hoy)
// ============================================================================

echo "========================================\n";
echo "TEST 6: Informe del Mes Actual (Parcial)\n";
echo "========================================\n\n";

try {
    $startDate = \Carbon\Carbon::now()->startOfMonth();
    $endDate = \Carbon\Carbon::now();
    
    echo "Periodo: {$startDate->format('d/m/Y')} - {$endDate->format('d/m/Y')}\n";
    echo "⚠️ NOTA: Este es un informe parcial del mes en curso\n\n";
    
    // Despachar job de reporte admin con fechas personalizadas
    $job = new \App\Jobs\SendMonthlyAdminCertificatesReportJob($startDate, $endDate);
    dispatch($job);
    
    echo "✅ Job despachado para periodo personalizado\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// INSTRUCCIONES FINALES
// ============================================================================

echo "========================================\n";
echo "INSTRUCCIONES FINALES\n";
echo "========================================\n\n";

echo "1. Asegúrese de que el Queue Worker esté ejecutándose:\n";
echo "   php artisan queue:work --queue=reports\n\n";

echo "2. Para procesar los jobs inmediatamente:\n";
echo "   php artisan queue:work --once --queue=reports\n\n";

echo "3. Ver logs en tiempo real:\n";
echo "   tail -f storage/logs/laravel.log | grep MonthlyReport\n\n";

echo "4. Ver logs específicos de informes mensuales:\n";
echo "   tail -f storage/logs/scheduled-certificates-monthly-reports.log\n\n";

echo "5. Las tareas programadas se ejecutarán automáticamente:\n";
echo "   - Informes a empresas: Último día del mes a las 22:00\n";
echo "   - Informe admin: Último día del mes a las 23:00\n\n";

echo "========================================\n";
echo "Testing completado\n";
echo "========================================\n\n";
