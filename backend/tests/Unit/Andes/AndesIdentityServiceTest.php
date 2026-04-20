<?php

namespace Tests\Unit\Andes;

use App\Andes\DTOs\IdentityValidationRequest;
use App\Andes\Enums\AndesTokenStatusEnum;
use App\Andes\Enums\AndesValidationTypeEnum;
use App\Andes\Exceptions\AndesAuthenticationException;
use App\Andes\Exceptions\AndesIdentityValidationException;
use App\Andes\Services\AndesIdentityService;
use App\Andes\Services\AndesTokenManager;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests unitarios para AndesIdentityService.
 * Usa Http::fake() para simular respuestas de ANDES ID REST API.
 * Sin RefreshDatabase — no toca BD.
 */
class AndesIdentityServiceTest extends TestCase
{
    private AndesTokenManager $tokenManager;
    private AndesIdentityService $service;

    private const API_URL = 'https://v2.andesid.com.co/api';
    private const FAKE_TOKEN = 'fake-bearer-token-123';

    protected function setUp(): void
    {
        parent::setUp();

        // Mock del TokenManager para evitar llamadas reales de autenticación
        $this->tokenManager = $this->createMock(AndesTokenManager::class);
        $this->tokenManager->method('getValidToken')->willReturn(self::FAKE_TOKEN);
        $this->tokenManager->method('refreshToken')->willReturn(self::FAKE_TOKEN);

        $this->service = new AndesIdentityService(
            tokenManager: $this->tokenManager,
            apiUrl:       self::API_URL,
        );
    }

    // ── startValidation ───────────────────────────────────────────────────────

    public function test_start_validation_con_otp_devuelve_response_correcta(): void
    {
        Http::fake([
            self::API_URL . '/solicitud_inicial' => Http::response([
                'estado'          => 0,
                'tipo_validacion' => 'PhoneSelection',
                'Token'           => 'session-token-abc',
                'mensaje'         => 'OTP enviado al celular',
            ], 200),
        ]);

        $dto = $this->buildRequest();
        $response = $this->service->startValidation($dto);

        $this->assertTrue($response->success);
        $this->assertSame('session-token-abc', $response->token);
        $this->assertSame(AndesValidationTypeEnum::OTP, $response->validationType);
        $this->assertSame(AndesTokenStatusEnum::EN_CURSO, $response->tokenStatus);
    }

    public function test_start_validation_con_cuestionario_devuelve_preguntas(): void
    {
        Http::fake([
            self::API_URL . '/solicitud_inicial' => Http::response([
                'estado'          => 0,
                'tipo_validacion' => 'ShowExam',
                'Token'           => 'session-token-xyz',
                'preguntas'       => [['id' => 1, 'texto' => '¿Cuál es su banco?']],
                'mensaje'         => 'Cuestionario generado',
            ], 200),
        ]);

        $dto = $this->buildRequest();
        $response = $this->service->startValidation($dto);

        $this->assertSame(AndesValidationTypeEnum::CUESTIONARIO, $response->validationType);
        $this->assertNotNull($response->questions);
        $this->assertCount(1, $response->questions);
    }

    public function test_start_validation_lanza_excepcion_si_http_error(): void
    {
        Http::fake([
            self::API_URL . '/solicitud_inicial' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $this->expectException(AndesIdentityValidationException::class);

        $this->service->startValidation($this->buildRequest());
    }

    // ── verifyOtp ─────────────────────────────────────────────────────────────

    public function test_verify_otp_exitoso_devuelve_estado_validado(): void
    {
        Http::fake([
            self::API_URL . '/verificar_OTP' => Http::response([
                'estado'  => 1,
                'mensaje' => 'Identidad validada correctamente',
            ], 200),
        ]);

        $response = $this->service->verifyOtp('my-token', '123456');

        $this->assertSame(AndesTokenStatusEnum::VALIDADO, $response->tokenStatus);
        $this->assertTrue($response->tokenStatus->isSuccessful());
    }

    public function test_verify_otp_fallido_devuelve_estado_fallido(): void
    {
        Http::fake([
            self::API_URL . '/verificar_OTP' => Http::response([
                'estado'  => 2,
                'mensaje' => 'Código incorrecto',
            ], 200),
        ]);

        $response = $this->service->verifyOtp('my-token', '000000');

        $this->assertSame(AndesTokenStatusEnum::FALLIDO, $response->tokenStatus);
        $this->assertFalse($response->tokenStatus->isSuccessful());
    }

    // ── verifyQuestions ────────────────────────────────────────────────────────

    public function test_verify_questions_exitoso(): void
    {
        Http::fake([
            self::API_URL . '/verificar_Preguntas' => Http::response([
                'estado'  => 1,
                'mensaje' => 'Cuestionario aprobado',
            ], 200),
        ]);

        $response = $this->service->verifyQuestions('my-token', '<answers><a>1</a></answers>');

        $this->assertTrue($response->tokenStatus->isSuccessful());
    }

    // ── resendOtp ─────────────────────────────────────────────────────────────

    public function test_resend_otp_no_lanza_excepcion_en_exito(): void
    {
        Http::fake([
            self::API_URL . '/reenviar_OTP' => Http::response(['mensaje' => 'OTP reenviado'], 200),
        ]);

        // No debe lanzar excepción
        $this->service->resendOtp('my-token', 'SMS');

        Http::assertSent(function (HttpRequest $request) {
            return str_contains($request->url(), '/reenviar_OTP')
                && $request['OTP_metod'] === 'SMS';
        });
    }

    // ── bypassToQuestions ─────────────────────────────────────────────────────

    public function test_bypass_to_questions_devuelve_preguntas(): void
    {
        Http::fake([
            self::API_URL . '/Bypass_Preguntas' => Http::response([
                'estado'          => 0,
                'tipo_validacion' => 'ShowExam',
                'Token'           => 'my-token',
                'preguntas'       => [['id' => 2, 'texto' => '¿En qué ciudad vive?']],
            ], 200),
        ]);

        $response = $this->service->bypassToQuestions('my-token');

        $this->assertSame(AndesValidationTypeEnum::CUESTIONARIO, $response->validationType);
        $this->assertNotNull($response->questions);
    }

    // ── checkTokenStatus ──────────────────────────────────────────────────────

    public function test_check_token_status_validado_retorna_1(): void
    {
        Http::fake([
            self::API_URL . '/verificar_Estado_Token' => Http::response([
                'estado' => 1,
            ], 200),
        ]);

        $result = $this->service->checkTokenStatus('1', '12345678', 'my-token');

        $this->assertSame(1, $result);
    }

    public function test_check_token_status_en_curso_retorna_0(): void
    {
        Http::fake([
            self::API_URL . '/verificar_Estado_Token' => Http::response([
                'estado' => 0,
            ], 200),
        ]);

        $result = $this->service->checkTokenStatus('1', '12345678', 'my-token');

        $this->assertSame(0, $result);
    }

    // ── Reintento automático por token expirado ──────────────────────────────

    public function test_reintenta_con_token_renovado_cuando_recibe_401(): void
    {
        Http::fake([
            self::API_URL . '/verificar_OTP' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push(['estado' => 1, 'mensaje' => 'OK'], 200),
        ]);

        $this->tokenManager->expects($this->once())->method('refreshToken');

        $response = $this->service->verifyOtp('expired-token', '123456');

        $this->assertSame(AndesTokenStatusEnum::VALIDADO, $response->tokenStatus);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function buildRequest(): IdentityValidationRequest
    {
        return new IdentityValidationRequest(
            idExpeditionDate:  '2005-06-15',
            idNumber:          '12345678',
            idType:            '1',
            recentPhoneNumber: '3001234567',
            lastName:          'GARCIA LOPEZ',
        );
    }
}

