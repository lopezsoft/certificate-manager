<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Presentation\Http\Controllers;

use App\Common\MessageExceptionResponse;
use App\Modules\Viafirma\Application\UseCases\GetKycLinkUseCase;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Controller REST para obtener el link KYC del proceso de acreditación.
 *
 * GET /api/v1/certificate-request/{id}/kyc-link
 *
 * El {id} es el ID de la solicitud legacy (certificate_requests.id).
 * Solo retorna el link cuando Viafirma tiene la solicitud en estado 'accreditation'.
 */
class KycLinkController extends Controller
{
    public function __construct(
        private readonly GetKycLinkUseCase $useCase,
    ) {}

    /**
     * @OA\Get(
     *     path="/certificate-request/{id}/kyc-link",
     *     tags={"Viafirma"},
     *     summary="Obtener link del portal KYC de acreditación",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"),
     *         description="ID de la solicitud de certificado (certificate_requests.id)"),
     *     @OA\Response(response=200, description="Link KYC generado", @OA\JsonContent(
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="link", type="string", example="https://kyc.viafirma.com/...")
     *         )
     *     )),
     *     @OA\Response(response=422, description="La solicitud no está en estado 'accreditation'"),
     *     @OA\Response(response=404, description="Solicitud no encontrada"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Resolver el trámite Viafirma asociado a la solicitud legacy
            $viafirmaRequest = ViafirmaCertificateRequest::where('certificate_request_id', $id)
                ->firstOrFail();

            $link = $this->useCase->handle($viafirmaRequest->id);

            return response()->json([
                'success' => true,
                'message' => 'Link KYC generado exitosamente.',
                'data'    => ['link' => $link],
            ]);
        } catch (ViafirmaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
