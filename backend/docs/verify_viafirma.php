<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Test RevokeCertificateUseCase can be resolved from the container
    $useCase1 = app(\App\Modules\Viafirma\Application\UseCases\RevokeCertificateUseCase::class);
    echo "RevokeCertificateUseCase: OK\n";

    $useCase2 = app(\App\Modules\Viafirma\Application\UseCases\GetKycLinkUseCase::class);
    echo "GetKycLinkUseCase: OK\n";

    // Test enum instantiation
    $reason = \App\Modules\Viafirma\Domain\Enums\RevocationReason::from(0);
    echo "RevocationReason::from(0) = " . $reason->label() . "\n";

    $reason2 = \App\Modules\Viafirma\Domain\Enums\RevocationReason::from(9);
    echo "RevocationReason::from(9) = " . $reason2->label() . "\n";

    // Test InternalState::REVOKED exists
    $state = \App\Modules\Viafirma\Domain\Enums\InternalState::REVOKED;
    echo "InternalState::REVOKED = " . $state->value . "\n";
    echo "isTerminal: " . ($state->isTerminal() ? 'true' : 'false') . "\n";

    echo "\n✓ All checks passed!\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "In: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
