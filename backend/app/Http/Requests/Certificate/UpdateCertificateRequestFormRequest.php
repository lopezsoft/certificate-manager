<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para la actualización de solicitudes de certificado.
 *
 * Centraliza la validación que antes estaba en CertificateRequestService.
 */
class UpdateCertificateRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id'               => ['required', 'integer', 'exists:cities,id'],
            'identity_document_id'  => ['required', 'integer', 'exists:identity_documents,id'],
            'type_organization_id'  => ['required', 'integer', 'exists:type_organization,id'],
            'document_number'       => ['required', 'string', 'max:30'],
            'address'               => ['required', 'string', 'max:255'],
            'legal_representative'  => ['required', 'string', 'max:120'],
            'company_name'          => ['required', 'string', 'max:120'],
            'dni'                   => ['required', 'string', 'max:30'],
            'life'                  => ['required', 'integer'],
            'info'                  => ['string', 'max:255', 'nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'city_id.required'              => 'La ciudad es requerida',
            'city_id.exists'                => 'La ciudad no existe',
            'identity_document_id.required' => 'El tipo de documento es requerido',
            'identity_document_id.exists'   => 'El tipo de documento no existe',
            'type_organization_id.required' => 'El tipo de organización es requerido',
            'type_organization_id.exists'   => 'El tipo de organización no existe',
            'dni.required'                  => 'El NIT es requerido',
            'document_number.required'      => 'El número de documento del representante legal es requerido',
            'company_name.required'         => 'La razón social es requerida',
            'address.required'              => 'La dirección es requerida',
            'legal_representative.required' => 'El nombre del representante legal es requerido',
            'life.required'                 => 'La vigencia del certificado es requerida',
        ];
    }
}

