<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Common\VerificationDigit;
use App\Enums\DocumentStatusEnum;
use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use App\Models\FileManager;
use App\Modules\Company\CompanyQueries;
use App\Notifications\CertificateRequestCreateNotification;
use App\Notifications\CertificateRequestStatusNotification;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class CertificateRequestService
{
    // Crate a new certificate request
    public function createCertificateRequest(Request $request): JsonResponse
    {
        $messagesValidate = [
            'city_id.required'       => 'La ciudad es requerida',
            'city_id.exists'         => 'La ciudad no existe',
            'identity_document_id.required' => 'El tipo de documento es requerido',
            'identity_document_id.exists'   => 'El tipo de documento no existe',
            'type_organization_id.required' => 'El tipo de organización es requerido',
            'type_organization_id.exists'   => 'El tipo de organización no existe',
            'dni.required'              => 'El NIT es requerido',
            'document_number.required'  => 'El número de documento es del representante legal requerido',
            'company_name.required'     => 'La razón social es requerida',
            'address.required'          => 'La dirección es requerida',
            'legal_representative.required'=> 'El nombre del representante legal es requerido',
            'life.required' => 'La vigencia del certificado es requerida',
            'life.integer' => 'La vigencia del certificado debe ser un número entero',
        ];

        $request->validate([
            'city_id'       => ['required', 'integer', 'exists:cities,id'],
            'identity_document_id' => ['required', 'integer', 'exists:identity_documents,id'],
            'type_organization_id' => ['required', 'integer', 'exists:type_organization,id'],
            'document_number' => ['required', 'string', 'max:30'],
            'address'       => ['required', 'string', 'max:255'],
            'legal_representative' => ['required', 'string', 'max:120'],
            'company_name'  => ['required', 'string', 'max:120'],
            'dni'           => ['required', 'string', 'max:30'],
            'life'          => ['required', 'integer'],
        ], $messagesValidate);
        try {
            $size       = 0;
            $filesList  = $request->files ?? [];
            if (count($filesList) > 3) {
                throw new Exception('El número de archivos adjuntos supera los 3 soportados.', 400);
            }
            if(count($filesList) < 2) {
                throw new Exception('No se ha enviado la información de los archivos adjuntos.', 400);
            }
            foreach ($filesList as $file) {
                if ($file->getSize() > 2 * 1024 * 1024) { // 2 MB
                    throw new Exception('El tamaño de los archivos adjuntos supera los 2 MB soportados.', 400);
                }
            }
            foreach ($filesList as $file) {
                $size   += $file->getSize();
            }

            if ($size > 0) {
                $size   = round((($size / 1024) / 1024), 2);
                if ($size > 2.05) { // 2 MB
                    throw new Exception("El tamaño de los archivos adjuntos supera los 2 MB soportados. Tamaño adjunto: {$size} MB", 400);
                }
            }

            $company        = CompanyQueries::getCompany();
            $dni            = $request->dni;
            $dv            = VerificationDigit::getDigit($dni);
            $certificateExists = CertificateRequest::query()
                ->where('company_id', $company->id)
                ->where('dni', $dni)
                ->where('dv', $dv)
                ->whereIn('request_status', [
                    'DRAFT', 'SENT', 'PENDING', 'ACCEPTED', 'PROCESSING'
                ])
                ->first();
            if ($certificateExists) {
                throw new Exception('Ya existe una solicitud de certificado, en proceso, con el mismo NIT y DV.
                        Por favor verifique el estado de la solicitud.', 400);
            }


            $disk           = Storage::disk('attachment');
            $local          = Storage::disk('local');
            $fileXls        = $local->path("templates/template-data-certificate.xlsx");

            $reader         = new Xlsx();
            $spreadsheet    = $reader->load($fileXls);
            $spreadsheet->setActiveSheetIndex(0);
            $activeSheet = $spreadsheet->getActiveSheet();

            $year           = date('Y');
            $month          = date('m');
            $folderName     = "companies/{$company->id}/{$year}/{$month}/{$dni}{$dv}";
            $disk->makeDirectory($folderName);

            DB::beginTransaction();
            $certificate    = CertificateRequest::create([
                'company_id' => $company->id,
                'city_id' => $request->city_id,
                'identity_document_id' => $request->identity_document_id,
                'type_organization_id' => $request->type_organization_id,
                'document_number' => $request->document_number,
                'address' => $request->address,
                'legal_representative' => $request->legal_representative,
                'company_name' => $request->company_name,
                'dni' => $dni,
                'dv' => $dv,
                'info' => $request->input('info'),
                'life' => $request->input('life') ?? 1,
                'base_path' => $folderName,
            ]);
            // Change history status
            ChangeHistory::create([
                'certificate_request_id'=>  $certificate->id,
                'status'                =>  'DRAFT',
                'comments'              =>  'Solicitud de certificado creada',
                'user_of_change'        =>  'USER',
                'user_id'               =>  auth()->user()->id,
            ]);

            $activeSheet->setCellValue('C4', $certificate->company_name);
            $activeSheet->setCellValue('C5', "{$dni}-{$dv}");
            $activeSheet->setCellValue('C6', $certificate->address);
            $activeSheet->setCellValue('C7', $certificate->city->name_city);
            $activeSheet->setCellValue('C8', $certificate->legal_representative);
            $activeSheet->setCellValue('C9', $certificate->identity->document_name);
            $activeSheet->setCellValue('C10', $certificate->document_number);
            $activeSheet->setCellValue('C11', 'soporte@matias.com.co');
            $activeSheet->setCellValue('C12', $certificate->phone);
            $activeSheet->setCellValue('C13', $certificate->mobile);
            $activeSheet->setCellValue('C14', "{$certificate->life} año(s)");
            $activeSheet->setCellValue('C15', 'Factura electronica');

            // Guardar el archivo Excel
            $fileExport = Str::uuid() . '.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save("storage/{$fileExport}");

            $content        = Storage::disk('public')->get($fileExport);


            $path           = "{$folderName}/EXCEL-DATOS-CERTIFICADO.xlsx";
            $disk->put("{$path}", $content);
            Storage::disk('public')->delete($fileExport);
            $format         = pathinfo($path, PATHINFO_EXTENSION);
            $mimeType       = $disk->mimeType($path);
            $sizeFile       = $disk->size($path);
            $lastModified   = $disk->lastModified($path);
            FileManager::create([
                'certificate_request_id'    =>  $certificate->id,
                'file_name'         =>  'EXCEL-DATOS-CERTIFICADO.xlsx',
                'file_path'         =>  $path,
                'extension_file'    =>  $format,
                'mime_type'         =>  $mimeType,
                'file_size'         =>  $sizeFile,
                'last_modified'     =>  date('Y-m-d H:i:s', $lastModified),
                'status'            =>  'COMPLETED',
            ]);

            foreach ($filesList as $file) {
                $fileName       = $file->getClientOriginalName();
                $path           = "{$folderName}/{$fileName}";
                $disk->putFileAs($folderName, $file, $fileName);

                $format         = pathinfo($path, PATHINFO_EXTENSION);
                $mimeType       = $disk->mimeType($path);
                $sizeFile       = $disk->size($path);
                $lastModified   = $disk->lastModified($path);
                FileManager::create([
                    'certificate_request_id'    =>  $certificate->id,
                    'file_name'         =>  $fileName,
                    'file_path'         =>  $path,
                    'extension_file'    =>  $format,
                    'mime_type'         =>  $mimeType,
                    'file_size'         =>  $sizeFile,
                    'last_modified'     =>  date('Y-m-d H:i:s', $lastModified),
                    'status'            =>  'COMPLETED',
                ]);
            }
            DB::commit();

            Notification::route('mail', env('MAIL_SUPPORT_ADDRESS','soporte@matias.com.co'))
                ->notify(new CertificateRequestCreateNotification($certificate));

            return HttpResponseMessages::getResponse([
                'message'   => 'Solicitud de certificado creada exitosamente',
                'data'      => $certificate,
            ]);
        }catch ( Exception $e) {
            DB::rollBack();
            return MessageExceptionResponse::response($e);
        }
    }
    // Get all certificate requests
    public function getCertificateRequest(Request $request): JsonResponse
    {
        try {
            $company        = CompanyQueries::getCompany();
            $certificate    = CertificateRequest::query()
                ->where('company_id', $company->id)
                ->orderBy('created_at', 'desc')
                ->with([
                    'identity:id,document_name',
                    'organization:id,description',
                    'city:id,name_city',
                    'files:id,certificate_request_id,file_name,file_path,extension_file,mime_type,file_size,last_modified,status',
                    'files'
                ]);
            return HttpResponseMessages::getResponse([
                'message'       => 'Lista de solicitudes de certificados',
                'dataRecords'   => $certificate->paginate($request->input('limit', 15)),
            ]);
        }catch ( Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function getCertificateRequestById($id): JsonResponse
    {
        try {
            $company        = CompanyQueries::getCompany();
            $certificate    = CertificateRequest::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->with([
                    'identity:id,document_name',
                    'organization:id,description',
                    'city:id,name_city',
                    'files:id,certificate_request_id,file_name,file_path,extension_file,mime_type,file_size,last_modified,status',
                    'files'
                ])
                ->firstOrFail();
            return HttpResponseMessages::getResponse([
                'message'       => 'Solicitud de certificado',
                'dataRecords'   => [
                    'data'  => [$certificate]
                ],
            ]);
        }catch ( Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function updateCertificateRequest(Request $request, $id): JsonResponse
    {
        $messagesValidate = [
            'city_id.required'       => 'La ciudad es requerida',
            'city_id.exists'         => 'La ciudad no existe',
            'identity_document_id.required' => 'El tipo de documento es requerido',
            'identity_document_id.exists'   => 'El tipo de documento no existe',
            'type_organization_id.required' => 'El tipo de organización es requerido',
            'type_organization_id.exists'   => 'El tipo de organización no existe',
            'dni.required'              => 'El NIT es requerido',
            'document_number.required'  => 'El número de documento es del representante legal requerido',
            'company_name.required'     => 'La razón social es requerida',
            'address.required'          => 'La dirección es requerida',
            'legal_representative.required'=> 'El nombre del representante legal es requerido',
            'life.required' => 'La vigencia del certificado es requerida',
        ];

        $request->validate([
            'city_id'       => ['required', 'integer', 'exists:cities,id'],
            'identity_document_id' => ['required', 'integer', 'exists:identity_documents,id'],
            'type_organization_id' => ['required', 'integer', 'exists:type_organization,id'],
            'document_number' => ['required', 'string', 'max:30'],
            'address'       => ['required', 'string', 'max:255'],
            'legal_representative' => ['required', 'string', 'max:120'],
            'company_name'  => ['required', 'string', 'max:120'],
            'dni'           => ['required', 'string', 'max:30'],
            'life'          => ['required', 'integer'],
            'info'          => ['string', 'max:255', 'nullable'],
        ], $messagesValidate);
        try {
            $company = CompanyQueries::getCompany();
            $certificate = CertificateRequest::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->firstOrFail();
            $dni = $request->dni;
            $dv = VerificationDigit::getDigit($dni);
            $certificate->updateOnlyChanged($request, [
                'city_id' => $request->city_id,
                'identity_document_id' => $request->identity_document_id,
                'type_organization_id' => $request->type_organization_id,
                'document_number' => $request->document_number,
                'address' => $request->address,
                'legal_representative' => $request->legal_representative,
                'company_name' => $request->company_name,
                'dni' => $dni,
                'dv' => $dv,
                'info' => $request->input('info'),
                'life' => $request->input('life') ?? 1,
                'postal_code' => $request->input('postal_code'),
                'phone' => $request->input('phone'),
                'mobile' => $request->input('mobile'),
            ]);
            return HttpResponseMessages::getResponse([
                'message'   => 'Solicitud de certificado actualizada exitosamente',
                'data'      => $certificate,
            ]);
        } catch (Exception $e){
            return MessageExceptionResponse::response($e);
        }
    }

    public function deleteCertificateRequest($id): JsonResponse
    {
        try {
            $company = CompanyQueries::getCompany();
            $certificate = CertificateRequest::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->firstOrFail();
            $certificate->delete();
            return HttpResponseMessages::getResponse([
                'message'   => 'Solicitud de certificado eliminada exitosamente',
            ]);
        } catch (Exception $e){
            return MessageExceptionResponse::response($e);
        }
    }

    public function updateCertificateRequestStatus(Request $request, $id): JsonResponse
    {
        try {
            $company = CompanyQueries::getCompany();
            $certificate = CertificateRequest::query()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->firstOrFail();
            DB::beginTransaction();
            $certificate->update([
                'request_status' => $request->request_status,
            ]);
            // Change history status
            ChangeHistory::create([
                'certificate_request_id'=>  $certificate->id,
                'status'                =>  $request->request_status,
                'comments'              =>  $request->comments,
                'user_id'               =>  auth()->user()->id,
                'user_of_change'        =>  $request->user_of_change ?? 'USER',
            ]);
            DB::commit();
            if ($request->request_status == DocumentStatusEnum::getRejected() && $request->user_of_change == 'MANAGER') {
                $certificateCompany = $certificate->company;
                $messageData = (object) [
                    'company'   => $certificateCompany,
                    'data'      => $certificate,
                    'comments'  => $request->comments,
                ];
                Notification::route('mail', env('MAIL_SUPPORT_ADDRESS','soporte@matias.com.co'))
                    ->notify(new CertificateRequestStatusNotification($messageData));

                Notification::route('mail', $certificateCompany->email)
                    ->notify(new CertificateRequestStatusNotification($messageData));
            }
            return HttpResponseMessages::getResponse([
                'message'   => 'El estado de la solicitud se ha actualizada exitosamente',
                'data'      => $certificate,
            ]);
        } catch (Exception $e){
            DB::rollBack();
            return MessageExceptionResponse::response($e);
        }
    }

    public function getAllCertificateRequest(Request $request): JsonResponse
    {
        try {
            $company        = CompanyQueries::getCompany();
            $status         = $request->input('status');
            $certificate    = CertificateRequest::query()
                ->where('company_id', $company->id)
                ->whereNotIn('request_status', [
                    'DRAFT', 'DELETED', 'CANCELLED'
                ])
                ->orderBy('created_at', 'desc')
                ->with([
                    'identity:id,document_name',
                    'organization:id,description',
                    'city:id,name_city',
                    'files:id,certificate_request_id,file_name,file_path,extension_file,mime_type,file_size,last_modified,status',
                    'files'
                ]);
            if ($status) {
                $certificate->where('request_status', $status);
            }
            return HttpResponseMessages::getResponse([
                'message'       => 'Lista de solicitudes de certificados',
                'dataRecords'   => $certificate->paginate($request->input('limit', 15)),
            ]);
        }catch ( Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
