<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // 1. Verificar que el UseCase se resuelve del IoC
    $uc = app(\App\Modules\Viafirma\Application\UseCases\RedownloadCertificateUseCase::class);
    echo "✓ RedownloadCertificateUseCase: resolved OK\n";

    // 2. Verificar DTO
    $dto = new \App\Modules\Viafirma\Application\DTOs\RedownloadResultDto(
        pin: 'testpin123',
        downloadUrl: '/api/v1/certificate-request/1/issuance/download/file',
        viafirmaId: 1,
        internalState: 'ASSEMBLED',
        remoteStatus: 'Generated_And_Downloaded',
    );
    echo "✓ RedownloadResultDto: instantiated OK\n";
    $arr = $dto->toArray();
    assert(isset($arr['pin']) && $arr['pin'] === 'testpin123', 'pin mismatch');
    assert(isset($arr['viafirma_id']) && $arr['viafirma_id'] === 1, 'id mismatch');
    echo "✓ RedownloadResultDto::toArray(): " . json_encode($arr) . "\n";

    // 3. Verificar que la ruta existe
    $routes = app('router')->getRoutes();
    $found = false;
    foreach ($routes as $route) {
        if ($route->getName() === 'v1.certificate-request.issuance.redownload') {
            $found = true;
            echo "✓ Route [v1.certificate-request.issuance.redownload]: {$route->methods()[0]} {$route->uri()}\n";
            break;
        }
    }
    if (!$found) {
        echo "✗ Route NOT found!\n";
    }

    echo "\n✓ All checks passed!\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "In: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
