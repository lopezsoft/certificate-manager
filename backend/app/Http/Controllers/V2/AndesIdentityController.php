<?php

namespace App\Http\Controllers\V2;

use App\Andes\Contracts\AndesIdentityServiceContract;
use App\Andes\DTOs\IdentityValidationRequest;
use App\Andes\Enums\AndesTokenStatusEnum;
use App\Andes\Exceptions\AndesIdentityValidationException;
use App\Andes\Models\AndesCertificateRequest;
use App\Andes\Models\AndesIdentityValidation;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AndesIdentityController — Sprint 2
 * Gestiona el flujo de validación de identidad con ANDES ID REST API.
 */
class AndesIdentityController extends Controller
{
    public function __construct(
        private readonly AndesIdentityServiceContract $identityService,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'andes_certificate_request_id' => ['required', 'integer', 'exists:andes_certificate_requests,id'],
            'id_expedition_date'           => ['required', 'date_format:Y-m-d'],
        ]);

        $andesReq = AndesCertificateRequest::with('certificateRequest.identity', 'certificateRequest.city')
            ->findOrFail($data['andes_certificate_request_id']);

        $certReq = $andesReq->certificateRequest;

        try {
            $dto = new IdentityValidationRequest(
                idExpeditionDate:  $data['id_expedition_date'],
                idNumber:          preg_replace('/[^0-9]/', '', $certReq->document_number),
                idType:            (string) ($certReq->identity?->andes_code ?? ''),
                recentPhoneNumber: $certReq->mobile,
                lastName:          $this->extractLastName($certReq->legal_representative),
            );

            $response = $this->identityService->startValidation($dto);

            $validation = AndesIdentityValidation::create([
                'andes_certificate_request_id' => $andesReq->id,
                'validation_type'              => $response->validationType?->value ?? 'UNKNOWN',
                'token'                        => $response->token ?? '',
                'estado'                       => $response->tokenStatus->value,
                'raw_response'                 => $response->rawResponse,
                'attempts'                     => 1,
                'expires_at'                   => Carbon::now()->addHour(),
            ]);

            return response()->json([
                'data' => [
                    'validation_id'   => $validation->id,
                    'validation_type' => $response->validationType?->value,
                    'estado'          => $response->tokenStatus->value,
                    'message'         => $response->message,
                    'questions'       => $response->questions,
                ],
            ]);
        } catch (AndesIdentityValidationException $e) {
            Log::warning('[ANDES-ID] Error al iniciar validación.', ['msg' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'validation_id' => ['required', 'integer', 'exists:andes_identity_validations,id'],
            'otp_code'      => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $validation = AndesIdentityValidation::findOrFail($data['validation_id']);

        if ($validation->isExpired()) {
            return response()->json(['message' => 'El token de validación ha expirado.'], 422);
        }

        try {
            $response = $this->identityService->verifyOtp($validation->token, $data['otp_code']);
            $this->updateValidation($validation, $response->tokenStatus, $response->rawResponse);

            return response()->json([
                'data' => [
                    'estado'  => $response->tokenStatus->value,
                    'message' => $response->message ?? $response->tokenStatus->label(),
                    'success' => $response->tokenStatus->isSuccessful(),
                ],
            ]);
        } catch (AndesIdentityValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function verifyQuestions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'validation_id' => ['required', 'integer', 'exists:andes_identity_validations,id'],
            'answers_xml'   => ['required', 'string'],
        ]);

        $validation = AndesIdentityValidation::findOrFail($data['validation_id']);

        if ($validation->isExpired()) {
            return response()->json(['message' => 'El token de validación ha expirado.'], 422);
        }

        try {
            $response = $this->identityService->verifyQuestions($validation->token, $data['answers_xml']);
            $this->updateValidation($validation, $response->tokenStatus, $response->rawResponse);

            return response()->json([
                'data' => [
                    'estado'  => $response->tokenStatus->value,
                    'message' => $response->message ?? $response->tokenStatus->label(),
                    'success' => $response->tokenStatus->isSuccessful(),
                ],
            ]);
        } catch (AndesIdentityValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'validation_id' => ['required', 'integer', 'exists:andes_identity_validations,id'],
            'method'        => ['required', 'string', 'in:SMS,VOICE'],
        ]);

        $validation = AndesIdentityValidation::findOrFail($data['validation_id']);

        try {
            $this->identityService->resendOtp($validation->token, $data['method']);
            $validation->increment('attempts');

            return response()->json([
                'data' => ['message' => "OTP reenviado por {$data['method']}."],
            ]);
        } catch (AndesIdentityValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function bypassToQuestions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'validation_id' => ['required', 'integer', 'exists:andes_identity_validations,id'],
        ]);

        $validation = AndesIdentityValidation::findOrFail($data['validation_id']);

        try {
            $response = $this->identityService->bypassToQuestions($validation->token);

            $validation->update([
                'validation_type' => 'ShowExam',
                'questions_data'  => $response->questions,
                'raw_response'    => $response->rawResponse,
            ]);

            return response()->json([
                'data' => [
                    'validation_type' => 'ShowExam',
                    'questions'       => $response->questions,
                    'message'         => 'Validación cambiada a cuestionario.',
                ],
            ]);
        } catch (AndesIdentityValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function checkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'validation_id' => ['required', 'integer', 'exists:andes_identity_validations,id'],
        ]);

        $validation = AndesIdentityValidation::with('andesCertificateRequest.certificateRequest.identity')
            ->findOrFail($data['validation_id']);

        $certReq = $validation->andesCertificateRequest->certificateRequest;

        try {
            $estado = $this->identityService->checkTokenStatus(
                idType:   (string) ($certReq->identity?->andes_code ?? ''),
                idNumber: preg_replace('/[^0-9]/', '', $certReq->document_number),
                token:    $validation->token,
            );

            $statusEnum = AndesTokenStatusEnum::from($estado);
            $validation->update(['estado' => $estado]);

            if ($statusEnum->isSuccessful() && ! $validation->validated_at) {
                $validation->update(['validated_at' => now()]);
            }

            return response()->json([
                'data' => [
                    'estado'       => $estado,
                    'status_label' => $statusEnum->label(),
                    'validated'    => $statusEnum->isSuccessful(),
                    'is_final'     => $statusEnum->isFinal(),
                ],
            ]);
        } catch (AndesIdentityValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function updateValidation(AndesIdentityValidation $validation, AndesTokenStatusEnum $status, array $raw): void
    {
        $validation->update([
            'estado'       => $status->value,
            'raw_response' => $raw,
            'attempts'     => $validation->attempts + 1,
            'validated_at' => $status->isSuccessful() ? now() : $validation->validated_at,
        ]);
    }

    private function extractLastName(string $fullName): string
    {
        $parts = array_values(array_filter(explode(' ', trim($fullName))));
        $count = count($parts);

        if ($count >= 4) return implode(' ', array_slice($parts, -2));
        if ($count === 3) return $parts[2];
        return $parts[count($parts) - 1] ?? '';
    }
}
