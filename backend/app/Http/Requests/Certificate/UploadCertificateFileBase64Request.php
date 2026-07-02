<?php

namespace App\Http\Requests\Certificate;

use App\Services\Base64DecoderService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request para subir archivos en Base64 a una solicitud de certificado.
 *
 * Valida un array de attachments en Base64. Solo el campo `base64` es obligatorio.
 * Los campos `name`, `type` y `size` son opcionales y se extraen del contenido Base64 si no se proporcionan.
 */
class UploadCertificateFileBase64Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attachments'                => ['required', 'array', 'min:1'],
            'attachments.*.base64'       => ['required', 'string'],
            'attachments.*.name'         => ['nullable', 'string', 'max:255'],
            'attachments.*.type'         => ['nullable', 'string', 'max:100'],
            'attachments.*.size'         => ['nullable', 'integer'],
            'document_type'              => ['nullable', 'string', 'in:ATTACHED,PAYMENT'],
            'pin'                        => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.required'           => 'El array de attachments es requerido',
            'attachments.array'              => 'Los attachments deben ser un array',
            'attachments.min'                => 'Debe enviar al menos 1 archivo',
            'attachments.*.base64.required'  => 'El contenido en base64 es requerido para cada archivo',
            'attachments.*.base64.string'    => 'El contenido en base64 debe ser un texto',
            'attachments.*.name.string'      => 'El nombre del archivo debe ser un texto',
            'attachments.*.name.max'         => 'El nombre del archivo no puede exceder 255 caracteres',
            'attachments.*.type.string'      => 'El tipo debe ser un texto',
            'attachments.*.type.max'         => 'El tipo no puede exceder 100 caracteres',
            'attachments.*.size.integer'     => 'El tamaño debe ser un número entero',
            'document_type.in'               => 'El tipo de documento debe ser ATTACHED o PAYMENT',
            'pin.string'                     => 'El PIN debe ser un texto',
            'pin.max'                        => 'El PIN no puede exceder 50 caracteres',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $decoder = app(Base64DecoderService::class);
                $attachments = $this->input('attachments', []);

                if (!is_array($attachments) || empty($attachments)) {
                    $validator->errors()->add('attachments', 'Debe enviar al menos 1 archivo');
                    return;
                }

                $maxFileSize = 2 * 1024 * 1024; // 2 MB
                $allowedMimeTypes = [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'application/zip',
                    'application/x-zip-compressed',
                ];

                foreach ($attachments as $index => $attachment) {
                    if (!isset($attachment['base64'])) {
                        $validator->errors()->add("attachments.{$index}.base64", 'El contenido en base64 es requerido');
                        continue;
                    }

                    $base64String = $attachment['base64'];

                    // Validar que el Base64 sea válido
                    if (!$decoder->isValid($base64String)) {
                        $validator->errors()->add("attachments.{$index}.base64", 'El contenido en base64 no es válido');
                        continue;
                    }

                    // Decodificar y obtener metadatos
                    try {
                        $metadata = $decoder->decodeWithMetadata($base64String);
                        $binaryContent = $metadata['binary_content'];
                        $mimeType = $metadata['mime_type'];
                        $fileSize = strlen($binaryContent);
                    } catch (\Exception $e) {
                        $validator->errors()->add("attachments.{$index}.base64", 'Error al decodificar el archivo: ' . $e->getMessage());
                        continue;
                    }

                    // Validar tamaño máximo (2 MB)
                    if ($fileSize > $maxFileSize) {
                        $mb = round($fileSize / 1024 / 1024, 2);
                        $validator->errors()->add("attachments.{$index}.base64", "El archivo supera el tamaño máximo de 2 MB (tamaño: {$mb} MB)");
                        continue;
                    }

                    // Validar MIME type permitido
                    $finalMimeType = $mimeType;
                    if (!$finalMimeType) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $finalMimeType = finfo_buffer($finfo, $binaryContent);
                        finfo_close($finfo);
                    }

                    if (!in_array($finalMimeType, $allowedMimeTypes)) {
                        $validator->errors()->add("attachments.{$index}.base64", "Tipo de archivo no permitido. Solo se aceptan PDF, imágenes (JPG, PNG, GIF) y archivos ZIP. Detectado: {$finalMimeType}");
                        continue;
                    }
                }
            }
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Los datos de la solicitud son inválidos.',
            'errors'  => $validator->errors()
        ], 400));
    }
}
