<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Http\Request;

/**
 * DTO inmutable para filtros de consulta de solicitudes de certificado.
 *
 * Reemplaza el paso directo del objeto Request a la capa de servicios,
 * garantizando un contrato claro y tipado.
 */
final readonly class CertificateRequestFiltersDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?int    $customerId = null,
        public int     $limit = 15,
    ) {}

    /**
     * Factoría desde un Request HTTP.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            search:     $request->input('query'),
            status:     $request->input('request_status'),
            startDate:  $request->input('start_date'),
            endDate:    $request->input('end_date'),
            customerId: $request->filled('company_id') ? (int) $request->input('company_id') : null,
            limit:      (int) $request->input('limit', 15),
        );
    }

    /**
     * Convierte a array para el Repository.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search'      => $this->search,
            'status'      => $this->status,
            'start_date'  => $this->startDate,
            'end_date'    => $this->endDate,
            'customer_id' => $this->customerId,
            'limit'       => $this->limit,
        ];
    }
}
