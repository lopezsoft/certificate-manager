<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Persistence;

use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;

final class ViafirmaCertificateRequestRepository implements ViafirmaCertificateRequestRepositoryContract
{
    public function findOrFail(int $id): ViafirmaCertificateRequest
    {
        return ViafirmaCertificateRequest::query()
            ->with('state')
            ->findOrFail($id);
    }

    public function findByCodRequest(string $codRequest): ?ViafirmaCertificateRequest
    {
        return ViafirmaCertificateRequest::query()
            ->with('state')
            ->where('cod_request', $codRequest)
            ->first();
    }

    public function findByCertificateRequestId(int $certificateRequestId): ?ViafirmaCertificateRequest
    {
        return ViafirmaCertificateRequest::query()
            ->with('state')
            ->where('certificate_request_id', $certificateRequestId)
            ->first();
    }

    public function findByPublicId(string $publicId): ?ViafirmaCertificateRequest
    {
        return ViafirmaCertificateRequest::query()
            ->with('state')
            ->where('public_id', $publicId)
            ->first();
    }

    public function create(array $attributes): ViafirmaCertificateRequest
    {
        // Separar atributos de identidad y de estado
        $stateAttributes = [
            'internal_state',
            'remote_status',
            'key_vault_ref',
            'csr_fingerprint',
            'csr_pem',
            'p7b_storage_path',
            'p12_storage_path',
            'p12_password_ref',
            'request_payload',
            'last_status_response',
            'poll_attempts',
            'next_poll_at',
            'last_polled_at',
            'submitted_at',
            'downloaded_at',
            'assembled_at',
            'expires_at',
            'last_error_code',
            'last_error_message',
            'revocation_request_code',
            'revoked_at',
            'auto_redownload_attempts',
        ];

        $identityData = array_diff_key($attributes, array_flip($stateAttributes));
        $stateData    = array_intersect_key($attributes, array_flip($stateAttributes));

        /** @var ViafirmaCertificateRequest $entity */
        $entity = ViafirmaCertificateRequest::create($identityData);

        // Crear el registro de estado asociado
        $entity->state()->create($stateData);

        return $entity->load('state');
    }

    public function save(ViafirmaCertificateRequest $request): bool
    {
        return $request->save();
    }
}
