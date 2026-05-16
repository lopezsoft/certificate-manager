<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Persistence;

use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;

final class ViafirmaCertificateRequestRepository implements ViafirmaCertificateRequestRepositoryContract
{
    public function findOrFail(int $id): ViafirmaCertificateRequest
    {
        return ViafirmaCertificateRequest::query()->findOrFail($id);
    }

    public function findByCodRequest(string $codRequest): ?ViafirmaCertificateRequest
    {
        return ViafirmaCertificateRequest::query()->where('cod_request', $codRequest)->first();
    }

    public function findByCertificateRequestId(int $certificateRequestId): ?ViafirmaCertificateRequest
    {
        return ViafirmaCertificateRequest::query()
            ->where('certificate_request_id', $certificateRequestId)
            ->first();
    }

    public function create(array $attributes): ViafirmaCertificateRequest
    {
        return ViafirmaCertificateRequest::create($attributes);
    }

    public function save(ViafirmaCertificateRequest $request): bool
    {
        return $request->save();
    }
}

