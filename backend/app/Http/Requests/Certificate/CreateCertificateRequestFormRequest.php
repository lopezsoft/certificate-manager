<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para la creación de solicitudes de certificado.
 *
 * Centraliza la validación que antes estaba en CertificateRequestService.
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
            'entity_document_type_id'  => ['sometimes', 'integer', 'exists:entity_document_types,id'],
            'document_number'          => ['required', 'string', 'max:30'],
            'address'                  => ['required', 'string', 'max:255'],
            'legal_representative'     => ['required_without_all:legal_rep_first_name,legal_rep_last_name', 'string', 'max:120'],
            'legal_rep_first_name'     => ['nullable', 'string', 'max:120'],
            'legal_rep_last_name'      => ['nullable', 'string', 'max:120'],
            'legal_rep_email'          => ['required', 'email:rfc,dns', 'max:250'],
            'company_name'             => ['required', 'string', 'max:120'],
            'dni'                      => ['required', 'string', 'max:30'],
            'life'                     => ['required', 'integer'],
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
            'entity_document_type_id.exists'   => 'El tipo de documento constitutivo no existe',
            'dni.required'                     => 'El NIT es requerido',
            'document_number.required'         => 'El número de documento del representante legal es requerido',
            'company_name.required'            => 'La razón social es requerida',
            'address.required'                 => 'La dirección es requerida',
            'legal_representative.required_without_all' => 'El nombre completo del representante es requerido si no se proporcionan los nombres separados',
            'legal_rep_first_name.string'      => 'El nombre del representante debe ser un texto',
            'legal_rep_last_name.string'       => 'Los apellidos del representante deben ser un texto',
            'legal_rep_email.required'         => 'El correo del representante legal es requerido',
            'legal_rep_email.email'            => 'El correo del representante legal no es válido o no existe',
            'life.required'                    => 'La vigencia del certificado es requerida',
            'life.integer'                     => 'La vigencia del certificado debe ser un número entero',
        ];
    }
}


