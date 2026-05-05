<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\CertificateRequest;

/**
 * Contrato para el repositorio de solicitudes de certificado.
 *
 * Abstrae las queries Eloquent del módulo v1, permitiendo
 * intercambiar la implementación sin afectar los servicios.
 */
interface CertificateRequestRepositoryContract
{
    /**
     * Obtener solicitudes de certificado paginadas para una empresa.
     *
     * @param int         $companyId
     * @param array{
     *     search?: string|null,
     *     status?: string|null,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     customer_id?: int|null,
     *     limit?: int
     * } $filters
     * @return LengthAwarePaginator
     */
    public function findByCompany(int $companyId, array $filters = []): LengthAwarePaginator;

    /**
     * Obtener una solicitud específica por ID y empresa.
     */
    public function findOneByCompany(int $companyId, int $certificateId): ?CertificateRequest;

    /**
     * Obtener todas las solicitudes (vista admin) con filtros.
     *
     * @param array{
     *     search?: string|null,
     *     status?: string|null,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     limit?: int
     * } $filters
     * @return LengthAwarePaginator
     */
    public function findAll(array $filters = []): LengthAwarePaginator;
}
