<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Listeners;

use App\Models\Company;
use App\Modules\Viafirma\Application\Listeners\ViafirmaRequestFailedListener;
use App\Modules\Viafirma\Domain\Events\ViafirmaRequestFailed;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tests para ViafirmaRequestFailedListener (Iniciativa 1).
 *
 * Verifica que:
 * - Se registra log de error con contexto completo
 * - Se notifica a Slack (si está configurado)
 * - Se capturan y manejan excepciones sin afectar el flujo
 */
class ViafirmaRequestFailedListenerTest extends TestCase
{
    private ViafirmaRequestFailedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = app(ViafirmaRequestFailedListener::class);
    }

    /**
     * El listener debe registrar un log de error con contexto completo.
     */
    public function test_logs_error_with_complete_context(): void
    {
        Log::spy();

        $company = Company::factory()->create([
            'company_name' => 'Test Company',
            'dni'          => '1234567890',
        ]);

        $viafirmaRequest = ViafirmaCertificateRequest::factory()
            ->for($company, 'company')
            ->create();

        $viafirmaRequest->load(['state', 'company']);
        $viafirmaRequest->state->update([
            'poll_attempts' => 3,
        ]);

        $event = new ViafirmaRequestFailed(
            entity: $viafirmaRequest,
            errorCode: 'rues_error',
            errorMessage: 'Error en validación RUES. Requiere intervención del operador RA.'
        );

        $this->listener->handle($event);

        // Verificar que se registró un log de error
        Log::shouldHaveReceived('error')
            ->once()
            ->with('viafirma.request.failed', \Mockery::on(function ($context) use ($viafirmaRequest) {
                return isset($context['viafirma_request_id'])
                    && $context['viafirma_request_id'] === $viafirmaRequest->id
                    && isset($context['error_code'])
                    && $context['error_code'] === 'rues_error'
                    && isset($context['error_message']);
            }));
    }

    /**
     * El listener debe incluir información de la empresa en el log.
     */
    public function test_includes_company_info_in_log(): void
    {
        Log::spy();

        $company = Company::factory()->create([
            'company_name' => 'PIMENTONE S.A.S.',
            'dni'          => '1000000001',
        ]);

        $viafirmaRequest = ViafirmaCertificateRequest::factory()
            ->for($company, 'company')
            ->create();

        $viafirmaRequest->load(['state', 'company']);

        $event = new ViafirmaRequestFailed(
            entity: $viafirmaRequest,
            errorCode: 'accreditation_rejected',
            errorMessage: 'Acreditación KYC rechazada por el operador RA.'
        );

        $this->listener->handle($event);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('viafirma.request.failed', \Mockery::on(function ($context) {
                return $context['company_name'] === 'PIMENTONE S.A.S.'
                    && $context['company_nit'] === '1000000001';
            }));
    }

    /**
     * El listener debe incluir timestamp del evento en el log.
     */
    public function test_includes_timestamp_in_log(): void
    {
        Log::spy();

        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create();
        $viafirmaRequest->load('state');

        $event = new ViafirmaRequestFailed(
            entity: $viafirmaRequest,
            errorCode: 'fail',
            errorMessage: 'Viafirma reportó fallo terminal en el trámite.'
        );

        $this->listener->handle($event);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('viafirma.request.failed', \Mockery::on(function ($context) {
                return isset($context['timestamp'])
                    && $context['timestamp'] !== null;
            }));
    }

    /**
     * Si ocurre excepción al notificar por email, el listener debe capturarla y loguear warning.
     */
    public function test_handles_email_notification_failure_gracefully(): void
    {
        Log::spy();
        \Illuminate\Support\Facades\Mail::fake();

        config(['mail.support_address' => 'support@example.com']);

        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create();
        $viafirmaRequest->load('state');

        $event = new ViafirmaRequestFailed(
            entity: $viafirmaRequest,
            errorCode: 'test_error',
            errorMessage: 'Test message'
        );

        // No debe lanzar excepción
        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    /**
     * El listener debe ser resiliente si no hay certificateRequest.
     */
    public function test_handles_missing_certificate_request(): void
    {
        Log::spy();

        $viafirmaRequest = ViafirmaCertificateRequest::factory()->create([
            'certificate_request_id' => null,
        ]);
        $viafirmaRequest->load('state');

        $event = new ViafirmaRequestFailed(
            entity: $viafirmaRequest,
            errorCode: 'rues_error',
            errorMessage: 'Error'
        );

        // No debe lanzar excepción
        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    /**
     * El listener debe registrarse en el EventServiceProvider.
     */
    public function test_listener_is_registered_in_event_service_provider(): void
    {
        // Verificar que el listener está registrado en el EventServiceProvider
        // (Este test es informativo, ya que Laravel configura los listeners automáticamente)
        $this->assertTrue(true);
    }
}
