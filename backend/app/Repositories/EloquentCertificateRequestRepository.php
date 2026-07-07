<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\CertificateRequestRepositoryContract;
use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Implementación Eloquent del repositorio de solicitudes de certificado.
 *
 * Centraliza TODAS las queries complejas que antes estaban dispersas
 * en CertificateRequestService.
 */
class EloquentCertificateRequestRepository implements CertificateRequestRepositoryContract
{
    /** Relaciones estándar para las vistas de certificados */
    private const DEFAULT_RELATIONS = [
        'identity:id,document_name',
        'organization:id,description',
        'city',
        'files:id,uuid,certificate_request_id,file_name,file_path,extension_file,mime_type,file_size,last_modified,status,document_type',
    ];

    /** Relaciones extendidas para la vista admin */
    private const ADMIN_RELATIONS = [
        'identity:id,document_name',
        'organization:id,description',
        'city',
        'files:id,uuid,certificate_request_id,file_name,file_path,extension_file,mime_type,file_size,last_modified,status,document_type',
        'company:id,company_name,dni,dv,address,email,phone,issuance_provider,has_agreement,active,uuid',
        'history'
    ];

    public function findByCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = CertificateRequest::query()
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->with(self::DEFAULT_RELATIONS);

        $this->applySearchFilter($query, $filters['search'] ?? null);
        $this->applyDateFilter($query, $filters['start_date'] ?? null, $filters['end_date'] ?? null);
        $this->applyStatusFilter($query, $filters['status'] ?? null);

        if (!empty($filters['customer_id'])) {
            $query->where('company_id', (int) $filters['customer_id']);
        }

        return $query->paginate($filters['limit'] ?? 15);
    }

    public function findOneByCompany(int $companyId, int $certificateId): ?CertificateRequest
    {
        return CertificateRequest::query()
            ->where('company_id', $companyId)
            ->where('id', $certificateId)
            ->with(self::DEFAULT_RELATIONS)
            ->first();
    }

    public function findAll(array $filters = []): LengthAwarePaginator
    {
        $query = CertificateRequest::query()
            ->orderBy('request_status')
            ->orderBy('created_at', 'desc')
            ->with(self::ADMIN_RELATIONS);

        $search = $filters['search'] ?? null;

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'LIKE', "%{$search}%")
                  ->orWhere('dni', 'LIKE', "%{$search}%")
                  ->orWhere('document_number', 'LIKE', "%{$search}%")
                  ->orWhere('legal_representative', 'LIKE', "%{$search}%");
            });
            $query->orWhereHas('company', function ($q) use ($search) {
                $q->where('company_name', 'LIKE', "%{$search}%");
            });
        }

        $this->applyDateFilter($query, $filters['start_date'] ?? null, $filters['end_date'] ?? null);

        $status = $filters['status'] ?? null;
        if (!empty($status)) {
            $query->where('request_status', $status);
        } else {
            $query->whereIn('request_status', CertificateRequestStatusEnum::adminDefaultStatuses());
        }

        return $query->paginate($filters['limit'] ?? 15);
    }

    /**
     * Aplica filtro de búsqueda por texto.
     */
    private function applySearchFilter($query, ?string $search): void
    {
        if (empty($search)) {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('company_name', 'LIKE', "%{$search}%")
              ->orWhere('dni', 'LIKE', "%{$search}%")
              ->orWhere('document_number', 'LIKE', "%{$search}%")
              ->orWhere('legal_representative', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Aplica filtro de rango de fechas.
     */
    private function applyDateFilter($query, ?string $startDate, ?string $endDate): void
    {
        if (empty($startDate) || empty($endDate)) {
            return;
        }

        $start = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $startDate)));
        $end   = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $endDate) . ' 23:59:59'));

        $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Aplica filtro de estado.
     */
    private function applyStatusFilter($query, ?string $status): void
    {
        if (empty($status)) {
            return;
        }

        $query->where('request_status', $status);
    }
}
