<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;

echo "=== Test: resetScope + CertificateIssuanceOrchestrator ===\n\n";

// Simular exactamente lo que hace resetScope()
echo "Llamando resetScope()...\n";
$app['log']->flushSharedContext();
if (method_exists($app['log'], 'withoutContext')) {
    $app['log']->withoutContext();
}
if (method_exists($app['db'], 'getConnections')) {
    foreach ($app['db']->getConnections() as $connection) {
        $connection->resetTotalQueryDuration();
        $connection->allowQueryDurationHandlersToRunAgain();
    }
}
$app->forgetScopedInstances();
Facade::clearResolvedInstances();
echo "resetScope() completado.\n\n";

// Intentar resolver el Orchestrator
echo "Resolviendo CertificateIssuanceOrchestrator...\n";
try {
    $orch = $app->make(\App\Services\Certificate\CertificateIssuanceOrchestrator::class);
    echo "✓ Orchestrator resuelto: " . get_class($orch) . "\n";
} catch (\Throwable $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "En: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Simular container->call([$job, 'handle'])
echo "\nSimulando container->call([\$job, 'handle'])...\n";
try {
    $job = new \App\Jobs\Certificate\AutoIssueViafirmaJob(636, 1);
    echo "✓ Job instanciado\n";

    $result = $app->call([$job, 'handle']);
    echo "✓ handle() llamado sin excepción\n";
} catch (\Throwable $e) {
    echo "Excepción (esperada si Viafirma retorna 400): " . get_class($e) . ": " . $e->getMessage() . "\n";
}

echo "\n=== Verificando job_debug.txt ===\n";
$path = storage_path('logs/job_debug.txt');
if (file_exists($path)) {
    echo "✓ job_debug.txt existe:\n" . file_get_contents($path) . "\n";
} else {
    echo "✗ job_debug.txt NO existe - handle() no fue llamado\n";
}

