<?php

namespace App\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Models\TermsVersion;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exposición pública de la versión vigente de los Términos y Condiciones.
 *
 * El front la consulta al cargar el formulario de solicitud y devuelve
 * `terms_version_id` en el POST de creación; el backend valida que siga
 * siendo la vigente.
 */
class TermsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/terms/current",
     *     tags={"Términos y Condiciones"},
     *     summary="Versión vigente de los Términos y Condiciones",
     *     description="Público. Devuelve id, versión, fecha, URL oficial y hash SHA-256 del texto vigente. El `id` debe enviarse como `terms_version_id` al crear una solicitud.",
     *     @OA\Response(response=200, description="Versión vigente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="version", type="string", example="1.0"),
     *                 @OA\Property(property="published_at", type="string", format="date-time"),
     *                 @OA\Property(property="source_url", type="string", example="https://maticerts.com/terminos/"),
     *                 @OA\Property(property="content_hash", type="string", example="dddf9aa9...")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No hay versión publicada")
     * )
     */
    public function current(): JsonResponse
    {
        try {
            $version = TermsVersion::current();

            if ($version === null) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'No existe una versión publicada de los Términos y Condiciones.',
                ]);
            }

            return HttpResponseMessages::getResponse([
                'message'     => 'Versión vigente de los Términos y Condiciones',
                'dataRecords' => $version->only(['id', 'version', 'published_at', 'source_url', 'content_hash']),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
