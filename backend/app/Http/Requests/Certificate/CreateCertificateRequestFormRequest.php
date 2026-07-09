<?php

namespace App\Http\Requests\Certificate;

use App\Models\Company;
use App\Models\EntityDocumentType;
use App\Modules\Company\CompanyQueries;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;
use App\Services\Base64DecoderService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request para la creación de solicitudes de certificado.
 *
 * Centraliza la validación que antes estaba en CertificateRequestService.
 * Soporta archivos en Base64 con detección automática de MIME type y nombre.
 */
class CreateCertificateRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id'                  => ['required', 'integer', 'exists:cities,id'],
            'identity_document_id'     => ['required', 'integer', 'exists:identity_documents,id'],
            'type_organization_id'     => ['required', 'integer', 'exists:type_organization,id'],
            'entity_document_type_id'  => ['required_if:type_organization_id,1', 'nullable', 'integer', 'exists:entity_document_types,id'],
            'document_number'          => ['required', 'string', 'max:30'],
            'address'                  => ['required', 'string', 'max:255'],
            'legal_representative'     => ['required_without_all:legal_rep_first_name,legal_rep_last_name', 'string', 'max:120'],
            'legal_rep_first_name'     => ['nullable', 'string', 'max:120'],
            'legal_rep_last_name'      => ['nullable', 'string', 'max:120'],
            'legal_rep_email'          => ['nullable', 'email:rfc,dns', 'max:250'],
            'company_name'             => ['required', 'string', 'max:120'],
            'dni'                      => ['required', 'string', 'max:30'],
            'life'                     => ['required', 'integer', 'in:1,2'],
            'attachments'                    => ['nullable', 'array'],
            'attachments.*.base64'           => ['required', 'string'],
            'attachments.*.name'             => ['nullable', 'string', 'max:255'],
            'attachments.*.type'             => ['nullable', 'string', 'max:100'],
            'attachments.*.size'             => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'city_id.required'                 => 'La ciudad es requerida',
            'city_id.exists'                   => 'La ciudad no existe',
            'identity_document_id.required'    => 'El tipo de documento es requerido',
            'identity_document_id.exists'      => 'El tipo de documento no existe',
            'type_organization_id.required'    => 'El tipo de organización es requerido',
            'type_organization_id.exists'      => 'El tipo de organización no existe',
            'entity_document_type_id.exists'      => 'El tipo de documento constitutivo no existe',
            'entity_document_type_id.required_if' => 'El tipo de documento constitutivo es requerido para Persona Jurídica',
            'dni.required'                     => 'El NIT es requerido',
            'document_number.required'         => 'El número de documento del representante legal es requerido',
            'company_name.required'            => 'La razón social es requerida',
            'address.required'                 => 'La dirección es requerida',
            'legal_representative.required_without_all' => 'El nombre completo del representante es requerido si no se proporcionan los nombres separados',
            'legal_rep_first_name.string'      => 'El nombre del representante debe ser un texto',
            'legal_rep_last_name.string'       => 'Los apellidos del representante deben ser un texto',
            'legal_rep_email.email'            => 'El correo del representante legal no es válido o no existe',
            'life.required'                    => 'La vigencia del certificado es requerida',
            'life.integer'                     => 'La vigencia del certificado debe ser un número entero',
            'life.in'                          => 'La vigencia del certificado debe ser 1 o 2 años',
            'attachments.array'                => 'Los adjuntos deben ser un array',
            'attachments.*.base64.required'    => 'El contenido en base64 es requerido para cada adjunto',
            'attachments.*.base64.string'      => 'El contenido en base64 debe ser un texto',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Obtener la empresa del usuario autenticado (sesión)
                $company = CompanyQueries::getCompany();
                $provider = $company->issuance_provider;

                if ($provider === 'viafirma') {
                    if (empty($this->input('legal_rep_email'))) {
                        $validator->errors()->add('legal_rep_email', 'El correo del representante legal es requerido para el proveedor Viafirma');
                    }

                    // Viafirma requiere nombres y apellidos del representante legal por separado
                    // (no basta con legal_representative como texto único).
                    if (empty($this->input('legal_rep_first_name')) || empty($this->input('legal_rep_last_name'))) {
                        $validator->errors()->add(
                            'legal_rep_first_name',
                            'Los nombres y apellidos del representante legal deben enviarse por separado (legal_rep_first_name, legal_rep_last_name) para el proveedor Viafirma.'
                        );
                    }
                }

                // Validación de adjuntos SOLO si el proveedor NO es Viafirma
                $requiresFiles = $provider !== 'viafirma';
                $attachments = $this->input('attachments', []);

                if ($requiresFiles) {
                    // Para proveedores que no son Viafirma, los adjuntos son requeridos
                    if (empty($attachments)) {
                        $validator->errors()->add('attachments', 'Los adjuntos son requeridos para este proveedor');
                        return;
                    }
                    $this->validateFilesStructure($validator, $attachments);
                }
            },
            function (Validator $validator) {
                $this->validateEntityDocumentTypeMapsToOrganizationType($validator);
            },
        ];
    }

    /**
     * Para Persona Jurídica (type_organization_id === 1), garantiza que el
     * entity_document_type_id seleccionado esté activo y que su `code`
     * mapee a un OrganizationType soportado por Viafirma.
     *
     * NOTA: el catálogo real de `entity_document_types` en BD puede no
     * coincidir con el seed trackeado en la migración (ver
     * database/migrations/2026_06_06_103900_create_entity_document_types_table.php).
     * Por eso se valida dinámicamente contra el enum OrganizationType::tryFrom()
     * en lugar de contra IDs fijos — funciona sea cual sea el catálogo real.
     */
    private function validateEntityDocumentTypeMapsToOrganizationType(Validator $validator): void
    {
        if ((int) $this->input('type_organization_id') !== 1) {
            return; // Solo aplica a Persona Jurídica (PJ)
        }

        $entityDocumentTypeId = $this->input('entity_document_type_id');
        if (empty($entityDocumentTypeId)) {
            return; // Ya cubierto por required_if
        }

        $entityDocType = EntityDocumentType::find($entityDocumentTypeId);
        if ($entityDocType === null) {
            return; // Ya cubierto por 'exists'
        }

        if (!$entityDocType->active) {
            $validator->errors()->add(
                'entity_document_type_id',
                'El tipo de documento constitutivo seleccionado no está activo.'
            );
            return;
        }

        if (OrganizationType::tryFrom($entityDocType->code) === null) {
            $validator->errors()->add(
                'entity_document_type_id',
                "El código '{$entityDocType->code}' del tipo de documento constitutivo no está soportado por Viafirma para perfiles FE-PJ."
            );
        }
    }

    /**
     * Valida la estructura y tamaños de los archivos en Base64.
     *
     * Soporta archivos con prefijo Data URI (data:application/pdf;base64,...)
     * y sin prefijo (solo el contenido Base64).
     */
    private function validateFilesStructure(Validator $validator, array $files): void
    {
        $decoder = app(Base64DecoderService::class);
        $maxFiles = config('certificate.file_upload.max_files', 3);
        $minFiles = config('certificate.file_upload.min_files', 2);
        $maxFileSize = config('certificate.file_upload.max_file_size', 7);
        $maxTotalSize = config('certificate.file_upload.max_total_size', 10);
        $maxFileSizeBytes = $maxFileSize * 1024 * 1024;
        $maxTotalBytes = $maxTotalSize * 1024 * 1024;

        // Validar cantidad de archivos
        if (count($files) > $maxFiles) {
            $validator->errors()->add('files', "El número de archivos supera los {$maxFiles} soportados.");
            return;
        }

        if (count($files) < $minFiles) {
            $validator->errors()->add('files', "Debe enviar al menos {$minFiles} archivos.");
            return;
        }

        $totalSize = 0;

        foreach ($files as $index => $file) {
            if (!isset($file['base64'])) {
                $validator->errors()->add("files.{$index}.base64", 'El contenido en base64 es requerido');
                continue;
            }

            // Decodificar y validar Base64 (soporta prefijo Data URI)
            try {
                $binaryContent = $decoder->decode($file['base64']);
            } catch (\Exception $e) {
                $validator->errors()->add("files.{$index}.base64", 'El contenido en base64 no es válido');
                continue;
            }

            $fileSize = strlen($binaryContent);
            $totalSize += $fileSize;

            // Validar tamaño individual
            if ($fileSize > $maxFileSizeBytes) {
                $mb = round($fileSize / 1024 / 1024, 2);
                $validator->errors()->add("files.{$index}", "El archivo supera el tamaño máximo de {$maxFileSize} MB (tamaño: {$mb} MB)");
            }
        }

        // Validar tamaño total
        if ($totalSize > $maxTotalBytes) {
            $mb = round($totalSize / 1024 / 1024, 2);
            $validator->errors()->add('files', "El tamaño total de los archivos supera los {$maxTotalSize} MB permitidos. Total: {$mb} MB");
        }
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Los datos de la solicitud son inválidos.',
            'errors'  => $validator->errors()
        ], 400));
    }
}


