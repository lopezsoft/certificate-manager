<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Contracts;

use App\Modules\Viafirma\Application\DTOs\ProfileDescriptor;

/**
 * Cliente del API Viafirma RA Colombia (PKCS#10).
 *
 * Patrón: Port (Hexagonal). Implementaciones: Guzzle / Saloon / Fake (tests).
 */
interface ViafirmaClient
{
    /**
     * GET /ra/available-profiles?codRa={ra}
     *
     * @return ProfileDescriptor[]
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException
     */
    public function getProfiles(string $raCode): array;

    // Métodos futuros (Sprint 2/3/4):
    // public function submitCsr(SubmitCsrInputDto $input): SubmitCsrResultDto;
    // public function getPublicId(string $codRequest): string;
    // public function getStatus(string $codRequest): StatusResultDto;
    // public function downloadP7b(string $codRequest): string; // binary
}

