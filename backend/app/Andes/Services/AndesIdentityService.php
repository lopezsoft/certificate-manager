<?php

namespace App\Andes\Services;

use App\Andes\Contracts\AndesIdentityServiceContract;
use App\Andes\DTOs\IdentityValidationRequest;
use App\Andes\DTOs\IdentityValidationResponse;
use App\Andes\Enums\AndesTokenStatusEnum;
use App\Andes\Exceptions\AndesAuthenticationException;
use App\Andes\Exceptions\AndesIdentityValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AndesIdentityService
 *
 * Comunicación con ANDES ID REST API v2 para verificación de identidad.
 * Patrón: thin HTTP client — no persiste nada, solo transforma respuestas a DTOs.
 *
 * Seguridad: nunca loguea tokens ni datos de identificación completos.
 */
class AndesIdentityService implements AndesIdentityServiceContract
{
    public function __construct(
        private readonly AndesTokenManager $tokenManager,
        private readonly string $apiUrl,
    ) {}

    /**
     * Inicia el proceso de validación de identidad.
     * POST /solicitud_inicial
     *
     * @throws AndesIdentityValidationException
     */
    public function startValidation(IdentityValidationRequest $dto): IdentityValidationResponse
    {
        Log::info('[ANDES-ID] Iniciando solicitud de validación de identidad.');

        $response = $this->post('/solicitud_inicial', $dto->toArray());

        return IdentityValidationResponse::fromApiResponse($response);
    }

    /**
     * Reenvía el OTP por el método indicado (SMS o VOICE).
     * POST /reenviar_OTP
     *
     * @param string $token      Token de sesión devuelto por /solicitud_inicial
     * @param string $method     'SMS' | 'VOICE'
     *
     * @throws AndesIdentityValidationException
     */
    public function resendOtp(string $token, string $method): void
    {
        Log::info('[ANDES-ID] Reenvío de OTP solicitado.', ['method' => $method]);

        $this->post('/reenviar_OTP', [
            'Token'      => $token,
            'OTP_metod'  => $method,
        ]);
    }

    /**
     * Verifica el código OTP ingresado por el usuario.
     * POST /verificar_OTP
     *
     * @throws AndesIdentityValidationException
     */
    public function verifyOtp(string $token, string $code): IdentityValidationResponse
    {
        Log::info('[ANDES-ID] Verificando OTP.');

        $response = $this->post('/verificar_OTP', [
            'Token'    => $token,
            'OTP_code' => $code,
        ]);

        return IdentityValidationResponse::fromApiResponse($response);
    }

    /**
     * Verifica las respuestas del cuestionario (XML).
     * POST /verificar_Preguntas
     *
     * @param string $answersXml  Respuestas en formato XML según spec ANDES
     *
     * @throws AndesIdentityValidationException
     */
    public function verifyQuestions(string $token, string $answersXml): IdentityValidationResponse
    {
        Log::info('[ANDES-ID] Verificando respuestas del cuestionario.');

        $response = $this->post('/verificar_Preguntas', [
            'Token'   => $token,
            'Answers' => $answersXml,
        ]);

        return IdentityValidationResponse::fromApiResponse($response);
    }

    /**
     * Cambia el método de validación de OTP a Cuestionario.
     * POST /Bypass_Preguntas
     *
     * @throws AndesIdentityValidationException
     */
    public function bypassToQuestions(string $token): IdentityValidationResponse
    {
        Log::info('[ANDES-ID] Solicitando cambio a validación por cuestionario.');

        $response = $this->post('/Bypass_Preguntas', [
            'Token' => $token,
        ]);

        return IdentityValidationResponse::fromApiResponse($response);
    }

    /**
     * Consulta el estado final del token de validación.
     * POST /verificar_Estado_Token
     *
     * @return int Estado: -1=No encontrado, 0=En curso, 1=Validado, 2=Fallido
     *
     * @throws AndesIdentityValidationException
     */
    public function checkTokenStatus(string $idType, string $idNumber, string $token): int
    {
        Log::info('[ANDES-ID] Consultando estado del token.');

        $response = $this->post('/verificar_Estado_Token', [
            'IdType'    => $idType,
            'IdNumber'  => $idNumber,
            'Token'     => $token,
        ]);

        return (int) ($response['estado'] ?? AndesTokenStatusEnum::NO_ENCONTRADO->value);
    }

    // ── HTTP helper ───────────────────────────────────────────────────────────

    /**
     * Ejecuta un POST autenticado contra la API de ANDES ID.
     * Si el token ha expirado (401), lo renueva y reintenta una vez.
     *
     * @throws AndesIdentityValidationException
     */
    private function post(string $endpoint, array $body): array
    {
        $token    = $this->tokenManager->getValidToken();
        $response = $this->doPost($endpoint, $body, $token);

        // Re-intento automático si el token expiró
        if ($response->status() === 401) {
            Log::info('[ANDES-ID] Token expirado, renovando y reintentando.');
            $token    = $this->tokenManager->refreshToken();
            $response = $this->doPost($endpoint, $body, $token);
        }

        if (! $response->successful()) {
            Log::error('[ANDES-ID] Error HTTP en endpoint.', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
            ]);
            throw new AndesIdentityValidationException(
                "ANDES ID devolvió error HTTP {$response->status()} en {$endpoint}",
                $response->json() ?? []
            );
        }

        return $response->json() ?? [];
    }

    private function doPost(string $endpoint, array $body, string $bearerToken): \Illuminate\Http\Client\Response
    {
        return Http::timeout(30)
            ->withToken($bearerToken)
            ->post("{$this->apiUrl}{$endpoint}", $body);
    }
}

