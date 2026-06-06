<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Certificate\CreateCertificateRequestCommand;
use App\Commands\Certificate\DeleteCertificateRequestCommand;
use App\Commands\Certificate\UpdateCertificateRequestCommand;
use App\Commands\Certificate\UpdateCertificateStatusCommand;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Contracts\CertificateRequestRepositoryContract;
use App\Models\Company;
use App\DTOs\CertificateRequestFiltersDTO;
use App\Handlers\Certificate\CreateCertificateRequestHandler;
use App\Handlers\Certificate\DeleteCertificateRequestHandler;
use App\Handlers\Certificate\UpdateCertificateRequestHandler;
use App\Handlers\Certificate\UpdateCertificateStatusHandler;
use App\Modules\Company\CompanyQueries;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateRequestService
{
    public function __construct(
        private readonly CreateCertificateRequestHandler     $createHandler,
        private readonly UpdateCertificateRequestHandler     $updateHandler,
        private readonly UpdateCertificateStatusHandler      $statusHandler,
        private readonly DeleteCertificateRequestHandler     $deleteHandler,
        private readonly CertificateRequestRepositoryContract $repository,
    ) {}

    // ── Comandos (escritura) ──────────────────────────────────────────────────

    public function createCertificateRequest(Request $request): JsonResponse
    {
        $company = CompanyQueries::getCompany();

        return $this->createHandler->handle(new CreateCertificateRequestCommand(
            companyId:             $company->id,
            cityId:                (int) $request->city_id,
            identityDocumentId:    (int) $request->identity_document_id,
            typeOrganizationId:    (int) $request->type_organization_id,
            entityDocumentTypeId:  (int) ($request->input('entity_document_type_id') ?? 1),
            documentNumber:        $request->document_number,
            address:               $request->address,
            legalRepresentative:   $request->legal_representative,
            legalRepEmail:         $request->input('legal_rep_email'),
            companyName:           $request->company_name,
            dni:                   $request->dni,
            life:                  (int) ($request->input('life') ?? 2),
            info:                  $request->input('info'),
            files:                 array_values($request->files->all()),
            userId:                auth()->id(),
        ));
    }

    public function updateCertificateRequest(Request $request, $id): JsonResponse
    {
        $company = CompanyQueries::getCompany();

        return $this->updateHandler->handle(new UpdateCertificateRequestCommand(
            certificateId:       (int) $id,
            companyId:           $company->id,
            cityId:              (int) $request->city_id,
            identityDocumentId:  (int) $request->identity_document_id,
            typeOrganizationId:  (int) $request->type_organization_id,
            documentNumber:      $request->document_number,
            address:             $request->address,
            legalRepresentative: $request->legal_representative,
            companyName:         $request->company_name,
            dni:                 $request->dni,
            life:                (int) ($request->input('life') ?? 1),
            info:                $request->input('info'),
            postalCode:          $request->input('postal_code'),
            phone:               $request->input('phone'),
            mobile:              $request->input('mobile'),
        ));
    }

    public function updateCertificateRequestStatus(Request $request, $id): JsonResponse
    {
        $company = CompanyQueries::getCompany();

        return $this->statusHandler->handle(new UpdateCertificateStatusCommand(
            certificateId:  (int) $id,
            companyId:      $company->id,
            requestStatus:  $request->request_status,
            comments:       $request->input('comments'),
            userOfChange:   $request->input('user_of_change', 'USER'),
            userId:         auth()->id(),
        ));
    }

    public function deleteCertificateRequest($id): JsonResponse
    {
        $company = CompanyQueries::getCompany();

        return $this->deleteHandler->handle(new DeleteCertificateRequestCommand(
            certificateId: (int) $id,
            companyId:     $company->id,
        ));
    }

    // ── Consultas (lectura) — Delegadas al Repository ────────────────────────

    public function getCertificateRequest(CertificateRequestFiltersDTO $filters): JsonResponse
    {
        try {
            $company = CompanyQueries::getCompany();

            return HttpResponseMessages::getResponse([
                'message'     => 'Lista de solicitudes de certificados',
                'dataRecords' => $this->repository->findByCompany($company->id, $filters->toArray()),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function getCertificateRequestById(int $id): JsonResponse
    {
        try {
            $company     = CompanyQueries::getCompany();
            $certificate = $this->repository->findOneByCompany($company->id, $id);

            return HttpResponseMessages::getResponse([
                'message'     => 'Solicitud de certificado',
                'dataRecords' => ['data' => [$certificate]],
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function getAllCertificateRequest(CertificateRequestFiltersDTO $filters): JsonResponse
    {
        try {
            return HttpResponseMessages::getResponse([
                'message'     => 'Lista de solicitudes de certificados',
                'dataRecords' => $this->repository->findAll($filters->toArray()),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Busca la última solicitud de certificado por DNI (NIT) en toda la plataforma.
     *
     * Permite al frontend autocompletar el formulario de nueva solicitud con los datos
     * del snapshot más reciente para ese NIT, sin importar qué empresa lo haya creado.
     */
    public function lookupByDni(string $dni): JsonResponse
    {
        try {
            $certificate = \App\Models\CertificateRequest::query()
                ->where('dni', $dni)
                ->orderByDesc('created_at')
                ->first();

            if (!$certificate) {
                return HttpResponseMessages::getResponse([
                    'message'     => 'No se encontraron solicitudes previas para el NIT proporcionado.',
                    'dataRecords' => null,
                ]);
            }

            return HttpResponseMessages::getResponse([
                'message'     => 'Datos de la última solicitud encontrada',
                'dataRecords' => [
                    'city_id'              => $certificate->city_id,
                    'identity_document_id' => $certificate->identity_document_id,
                    'type_organization_id' => $certificate->type_organization_id,
                    'dni'                  => $certificate->dni,
                    'dv'                   => $certificate->dv,
                    'document_number'      => $certificate->document_number,
                    'company_name'         => $certificate->company_name,
                    'address'              => $certificate->address,
                    'phone'                => $certificate->phone,
                    'mobile'               => $certificate->mobile,
                    'legal_representative' => $certificate->legal_representative,
                    'life'                 => $certificate->life,
                    'postal_code'          => $certificate->postal_code ?? null,
                ],
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Genera estadísticas de solicitudes de certificado por año para una empresa.
     *
     * Retorna el total de solicitudes por año y el desglose por estado,
     * ordenado del año más reciente al más antiguo.
     */
    public function getStatsByCompany(int $companyId): JsonResponse
    {
        try {
            $company = Company::query()->where('id', $companyId)->first();

            if (!$company) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'La empresa no existe.',
                ]);
            }

            // Totales por año
            $byYear = \App\Models\CertificateRequest::query()
                ->where('company_id', $companyId)
                ->selectRaw('YEAR(updated_at) as year, COUNT(*) as total')
                ->groupByRaw('YEAR(updated_at)')
                ->orderByDesc('year')
                ->get();

            // Desglose por año y estado
            $byYearAndStatus = \App\Models\CertificateRequest::query()
                ->where('company_id', $companyId)
                ->selectRaw('YEAR(updated_at) as year, request_status, COUNT(*) as total')
                ->groupByRaw('YEAR(updated_at), request_status')
                ->orderByDesc('year')
                ->get();

            // Armar estructura: { year, total, statuses: { STATUS: count, ... } }
            $stats = $byYear->map(function ($row) use ($byYearAndStatus) {
                $statuses = $byYearAndStatus
                    ->where('year', $row->year)
                    ->pluck('total', 'request_status')
                    ->toArray();

                return [
                    'year'     => (int) $row->year,
                    'total'    => (int) $row->total,
                    'statuses' => $statuses,
                ];
            });

            // Total global
            $grandTotal = $byYear->sum('total');

            // Estado de cuota
            $quotaService = app(\App\Services\QuotaService::class);
            $quotaStatus  = $quotaService->getQuotaStatus($companyId);

            return HttpResponseMessages::getResponse([
                'message'     => 'Estadísticas de solicitudes por año',
                'dataRecords' => [
                    'company_id'    => $companyId,
                    'company_name'  => $company->company_name,
                    'has_agreement' => (bool) ($company->has_agreement ?? false),
                    'grand_total'   => (int) $grandTotal,
                    'quota'         => $quotaStatus,
                    'data'          => $stats->values(),
                ],
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
