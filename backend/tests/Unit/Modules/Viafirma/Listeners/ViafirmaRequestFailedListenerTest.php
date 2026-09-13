<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Listeners;

use App\Models\CertificateRequest;
use App\Modules\Viafirma\Application\Listeners\ViafirmaRequestFailedListener;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Events\ViafirmaRequestFailed;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Support\Facades\Notification;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * Tests para ViafirmaRequestFailedListener (Iniciativa 1).
 *
 * Sin BD: el listener sólo LEE de las entidades, así que se montan en memoria
 * con setRelation() y se espía el logger inyectado. No se usan factories —
 * insertarían en base de datos.
 */
class ViafirmaRequestFailedListenerTest extends TestCase
{
    /** Captura lo que el listener loguea, sin escribir en ningún sitio. */
    private function makeSpyLogger(array &$captured): SafePemLogger
    {
        $inner = new class($captured) implements LoggerInterface {
            public function __construct(private array &$captured) {}

            public function log($level, $message, array $context = []): void
            {
                $this->captured[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
            }

            public function emergency($message, array $context = []): void { $this->log('emergency', $message, $context); }
            public function alert($message, array $context = []): void     { $this->log('alert', $message, $context); }
            public function critical($message, array $context = []): void  { $this->log('critical', $message, $context); }
            public function error($message, array $context = []): void     { $this->log('error', $message, $context); }
            public function warning($message, array $context = []): void   { $this->log('warning', $message, $context); }
            public function notice($message, array $context = []): void    { $this->log('notice', $message, $context); }
            public function info($message, array $context = []): void      { $this->log('info', $message, $context); }
            public function debug($message, array $context = []): void     { $this->log('debug', $message, $context); }
        };

        return new SafePemLogger($inner);
    }

    /**
     * Entidad completa en memoria: estado + solicitud asociada.
     */
    private function makeEntity(
        string  $companyName = 'Test Company',
        string  $dni = '1234567890',
        int     $pollAttempts = 3,
        bool    $withCertificateRequest = true,
    ): ViafirmaCertificateRequest {
        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;
        $entity->certificate_request_id = 42;
        $entity->cod_request = 'VF-TEST-001';

        $state = new ViafirmaCertificateRequestState();
        $state->internal_state = InternalState::FAILED_RECOVERABLE;
        $state->remote_status  = 'rues_error';
        $state->poll_attempts  = $pollAttempts;
        $state->submitted_at   = now()->subHours(2);
        $entity->setRelation('state', $state);

        if ($withCertificateRequest) {
            $certificateRequest = new CertificateRequest();
            $certificateRequest->id           = 42;
            $certificateRequest->company_name = $companyName;
            $certificateRequest->dni          = $dni;
            $entity->setRelation('certificateRequest', $certificateRequest);
        } else {
            $entity->setRelation('certificateRequest', null);
        }

        return $entity;
    }

    private function fire(ViafirmaCertificateRequest $entity, array &$captured, string $code = 'rues_error'): void
    {
        Notification::fake();

        $listener = new ViafirmaRequestFailedListener($this->makeSpyLogger($captured));

        $listener->handle(new ViafirmaRequestFailed(
            entity:       $entity,
            errorCode:    $code,
            errorMessage: 'Error en validación RUES. Requiere intervención del operador RA.',
        ));
    }

    /** @return array<string,mixed>|null */
    private function findLog(array $captured, string $message): ?array
    {
        foreach ($captured as $entry) {
            if ($entry['message'] === $message) {
                return $entry;
            }
        }

        return null;
    }

    public function test_logs_error_with_complete_context(): void
    {
        $captured = [];
        $this->fire($this->makeEntity(), $captured);

        $log = $this->findLog($captured, 'viafirma.request.failed');

        $this->assertNotNull($log, 'Debe registrarse el log de fallo.');
        $this->assertSame('error', $log['level']);

        foreach ([
            'viafirma_request_id',
            'certificate_request_id',
            'company_name',
            'company_nit',
            'internal_state',
            'remote_status',
            'error_code',
            'error_message',
            'poll_attempts',
            'timestamp',
        ] as $key) {
            $this->assertArrayHasKey($key, $log['context'], "Falta '{$key}' en el contexto.");
        }
    }

    public function test_includes_company_info_in_log(): void
    {
        $captured = [];
        $this->fire($this->makeEntity('PIMENTONE S.A.S.', '1000000001'), $captured);

        $log = $this->findLog($captured, 'viafirma.request.failed');

        $this->assertSame('PIMENTONE S.A.S.', $log['context']['company_name']);
        $this->assertSame('1000000001', $log['context']['company_nit']);
    }

    public function test_includes_timestamp_in_log(): void
    {
        $captured = [];
        $this->fire($this->makeEntity(), $captured);

        $log = $this->findLog($captured, 'viafirma.request.failed');

        $this->assertNotEmpty($log['context']['timestamp']);
        $this->assertNotFalse(
            strtotime($log['context']['timestamp']),
            'El timestamp debe ser una fecha válida.',
        );
    }

    public function test_logs_state_details(): void
    {
        $captured = [];
        $this->fire($this->makeEntity(pollAttempts: 7), $captured);

        $log = $this->findLog($captured, 'viafirma.request.failed');

        $this->assertSame(7, $log['context']['poll_attempts']);
        $this->assertSame('rues_error', $log['context']['remote_status']);
        $this->assertSame(InternalState::FAILED_RECOVERABLE->value, $log['context']['internal_state']);
    }

    /**
     * Sin solicitud asociada el listener no debe reventar: degrada a 'Unknown'.
     */
    public function test_handles_missing_certificate_request(): void
    {
        $captured = [];

        $this->fire($this->makeEntity(withCertificateRequest: false), $captured);

        $log = $this->findLog($captured, 'viafirma.request.failed');

        $this->assertNotNull($log, 'Debe loguear aunque falte la solicitud.');
        $this->assertSame('Unknown', $log['context']['company_name']);
        $this->assertSame('Unknown', $log['context']['company_nit']);
    }

    /**
     * Un fallo enviando el correo no debe propagarse: el listener es
     * síncrono y una excepción abortaría la transición de estado.
     */
    public function test_handles_email_notification_failure_gracefully(): void
    {
        $captured = [];

        // Sin dirección de soporte, notifyByEmail() corta por la rama de aviso.
        config(['mail.support_address' => null]);

        $this->fire($this->makeEntity(), $captured);

        $this->assertNotNull(
            $this->findLog($captured, 'viafirma.request.failed'),
            'El log principal debe emitirse aunque no haya correo configurado.',
        );
    }

    /**
     * Una expiración de KYC es informativa, no una alarma de fallo real.
     */
    public function test_expiration_is_logged_with_its_own_error_code(): void
    {
        $captured = [];

        $this->fire($this->makeEntity(), $captured, 'POLL_EXPIRED');

        $log = $this->findLog($captured, 'viafirma.request.failed');

        $this->assertSame('POLL_EXPIRED', $log['context']['error_code']);
    }
}
