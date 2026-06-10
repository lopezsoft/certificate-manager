<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Presentation\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Modules\Viafirma\Application\DTOs\RevokeInputDto;
use App\Modules\Viafirma\Application\UseCases\RevokeCertificateUseCase;
use App\Modules\Viafirma\Domain\Enums\RevocationReason;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Presentation\Http\Requests\RevokeCertificateFormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Controller REST para la revocación de certificados Viafirma.
 *
 * POST /api/v1/certificate-request/{id}/revoke
 *
 * El {id} es el ID de la solicitud legacy (certificate_requests.id).
 * Se resuelve el trámite Viafirma asociado antes de revocar.
 */
class RevocationController extends Controller
{
    public function __construct(
        private readonly RevokeCertificateUseCase $useCase,
    ) {}

    /**
     * @OA\Post(
     *     path="/certificate-request/{id}/revoke",
     *     tags={"Viafirma"},
     *     summary="Revocar un certificado Viafirma ya emitido",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"),
     *         description="ID de la solicitud de certificado (certificate_requests.id)"),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="revoking_code", type="string", description="Código de revocación recibido por el usuario"),
     *         @OA\Property(property="revocation_reason", type="integer", description="Motivo: 0,1,2,3,4,5,9,10"),
     *     )),
     *     @OA\Response(response=200, description="Certificado revocado exitosamente"),
     *     @OA\Response(response=400, description="Error de negocio (estado inválido o código erróneo)"),
     *     @OA\Response(response=404, description="Solicitud no encontrada"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function revoke(RevokeCertificateFormRequest $request, int $id): JsonResponse
    {
        try {
            // Resolver el trámite Viafirma asociado a la solicitud legacy
            $viafirmaRequest = ViafirmaCertificateRequest::where('certificate_request_id', $id)
                ->firstOrFail();

            $dto = new RevokeInputDto(
                viafirmaCertificateRequestId: $viafirmaRequest->id,
                revokingCode:                $request->string('revoking_code')->toString(),
                revocationReason:            RevocationReason::from((int) $request->input('revocation_reason')),
                revokedByUserId:             auth()->id() ?? 0,
            );

            $this->useCase->handle($dto);

            return HttpResponseMessages::getResponse([
                'message' => 'Certificado revocado exitosamente.',
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
