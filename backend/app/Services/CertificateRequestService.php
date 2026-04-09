<?php

namespace App\Services;

use App\Commands\Certificate\CreateCertificateRequestCommand;
use App\Commands\Certificate\DeleteCertificateRequestCommand;
use App\Commands\Certificate\UpdateCertificateRequestCommand;
use App\Commands\Certificate\UpdateCertificateStatusCommand;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Enums\CertificateRequestStatusEnum;
use App\Handlers\Certificate\CreateCertificateRequestHandler;
use App\Handlers\Certificate\DeleteCertificateRequestHandler;
use App\Handlers\Certificate\UpdateCertificateRequestHandler;
use App\Handlers\Certificate\UpdateCertificateStatusHandler;
use App\Models\CertificateRequest;
use App\Modules\Company\CompanyQueries;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateRequestService
{
    public function __construct(
        private readonly CreateCertificateRequestHandler $createHandler,
        private readonly UpdateCertificateRequestHandler $updateHandler,
        private readonly UpdateCertificateStatusHandler  $statusHandler,
        private readonly DeleteCertificateRequestHandler $deleteHandler,
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
            life:                 (int) ($request->input('life') ?? 1),
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

    // ── Consultas (lectura) ───────────────────────────────────────────────────

    public function getCertificateRequest(Request $request): JsonResponse
    {
        try {
            $company     = CompanyQueries::getCompany();
            $status      = $request->input('request_status');
            $search      = $request->input('query');
            $startDate   = $request->input('start_date');
            $endDate     = $request->input('end_date');
            $customerId  = $request->input('company_id');

            $query = CertificateRequest::query()
                ->where('company_id', $company->id)
                ->orderBy('created_at', 'desc')
                ->with([
                    'identity:id,document_name',
                    'organization:id,description',
                    'city:id,name_city',
                    'files:id,certificate_request_id,file_name,file_path,extension_file,mime_type,file_size,last_modified,status,document_type',
                ]);

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'LIKE', "%{$search}%")
                      ->orWhere('dni', 'LIKE', "%{$search}%")
                      ->orWhere('document_number', 'LIKE', "%{$search}%")
                      ->orWhere('legal_representative', 'LIKE', "%{$search}%");
                });
            }

            if ($startDate && $endDate) {
                $startDate = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $startDate)));
                $endDate   = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $endDate) . ' 23:59:59'));
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }

            if (!empty($status)) {
                $query->where('request_status', $status);
            }

            if (!empty($customerId)) {
                $query->where('company_id', $customerId);
            }

            return HttpResponseMessages::getResponse([
                'message'     => 'Lista de solicitudes de certificados',
                'dataRecords' => $query->paginate($request->input('limit', 15)),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function getCertificateRequestById($id): JsonResponse
    {
        try {
            $company     = CompanyQueries::getCompany();
            $certificate = CertificateRequest::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->with([
                    'identity:id,document_name',
                    'organization:id,description',
                    'city:id,name_city',
                    'files:id,certificate_request_id,file_name,file_path,extension_file,mime_type,file_size,last_modified,status,document_type',
                ])
                ->first();

            return HttpResponseMessages::getResponse([
                'message'     => 'Solicitud de certificado',
                'dataRecords' => ['data' => [$certificate]],
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function getAllCertificateRequest(Request $request): JsonResponse
    {
        try {
            $status    = $request->input('request_status');
            $search    = $request->input('query');
            $startDate = $request->input('start_date');
            $endDate   = $request->input('end_date');

            $query = CertificateRequest::query()
                ->orderBy('request_status')
                ->orderBy('created_at', 'desc')
                ->with([
                    'identity:id,document_name',
                    'organization:id,description',
                    'city:id,name_city',
                    'files:id,certificate_request_id,file_name,file_path,extension_file,mime_type,file_size,last_modified,status,document_type',
                    'company:id,company_name,dni,dv,address,email,phone',
                ]);

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

            if ($startDate && $endDate) {
                $startDate = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $startDate)));
                $endDate   = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $endDate) . ' 23:59:59'));
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }

            if (!empty($status)) {
                $query->where('request_status', $status);
            } else {
                $query->whereIn('request_status', CertificateRequestStatusEnum::adminDefaultStatuses());
            }

            return HttpResponseMessages::getResponse([
                'message'     => 'Lista de solicitudes de certificados',
                'dataRecords' => $query->paginate($request->input('limit', 15)),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
