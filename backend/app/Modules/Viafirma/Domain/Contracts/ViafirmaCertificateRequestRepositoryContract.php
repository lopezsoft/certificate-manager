<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Contracts;

use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;

/**
 * Repository del agregado raíz {@see ViafirmaCertificateRequest}.
 *
 * Patrón: Repository (Eric Evans). El UseCase depende SÓLO de esta interfaz.
 */
interface ViafirmaCertificateRequestRepositoryContract
{
    public function findOrFail(int $id): ViafirmaCertificateRequest;

    public function findByCodRequest(string $codRequest): ?ViafirmaCertificateRequest;

    public function findByCertificateRequestId(int $certificateRequestId): ?ViafirmaCertificateRequest;

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes): ViafirmaCertificateRequest;

    public function save(ViafirmaCertificateRequest $request): bool;
}

