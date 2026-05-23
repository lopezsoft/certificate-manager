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
            companyId:            $company->id,
            cityId:               (int) $request->city_id,
            identityDocumentId:   (int) $request->identity_document_id,
            typeOrganizationId:   (int) $request->type_organization_id,
            documentNumber:       $request->document_number,
            address:              $request->address,
            legalRepresentative:  $request->legal_representative,
            companyName:          $request->company_name,
            dni:                  $request->dni,
            life:                 (int) ($request->input('life') ?? 2),
            info:                 $request->input('info'),
            files:                array_values($request->files->all()),
            userId:               auth()->id(),
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
}
