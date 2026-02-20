<?php

namespace Tests\Unit\Handlers\Certificate;

use App\Commands\Certificate\UpdateCertificateStatusCommand;
use App\Enums\DocumentStatusEnum;
use App\Handlers\Certificate\UpdateCertificateStatusHandler;
use App\Models\CertificateRequest;
use App\Notifications\CertificateRequestStatusNotification;
use Illuminate\Support\Facades\Notification;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

/**
 * Tests unitarios para la lógica de notificaciones del UpdateCertificateStatusHandler.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 * Usa Notification::fake() + stdClass para simular el certificado y la compañía.
 */
class UpdateCertificateStatusHandlerNotificationTest extends TestCase
{
    private UpdateCertificateStatusHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new UpdateCertificateStatusHandler();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── sendStatusNotifications — lógica de envío ────────────────────────────

    public function test_no_se_envian_notificaciones_si_userOfChange_no_es_manager(): void
    {
        Notification::fake();

        $certificate = $this->makeCertificate();
        $command     = $this->makeCommand(
            requestStatus: DocumentStatusEnum::getRejected(),
            userOfChange:  'USER',
        );

        $this->invokePrivate('sendStatusNotifications', $certificate, $command);

        Notification::assertNothingSent();
    }

    public function test_no_se_envian_notificaciones_si_estado_no_es_rejected_ni_processed(): void
    {
        Notification::fake();

        $certificate = $this->makeCertificate();
        $command     = $this->makeCommand(
            requestStatus: 'PENDING',
            userOfChange:  'MANAGER',
        );

        $this->invokePrivate('sendStatusNotifications', $certificate, $command);

        Notification::assertNothingSent();
    }

    public function test_se_envian_notificaciones_cuando_status_es_rejected_y_manager(): void
    {
        Notification::fake();

        $certificate = $this->makeCertificate();
        $command     = $this->makeCommand(
            requestStatus: DocumentStatusEnum::getRejected(),
            userOfChange:  'MANAGER',
            comments:      'Documentos incompletos',
        );

        $this->invokePrivate('sendStatusNotifications', $certificate, $command);

        // Se deben enviar exactamente 2 notificaciones (soporte + empresa)
        Notification::assertSentOnDemand(CertificateRequestStatusNotification::class);
    }

    public function test_se_envian_notificaciones_cuando_status_es_processed_y_manager(): void
    {
        Notification::fake();

        $certificate = $this->makeCertificate();
        $command     = $this->makeCommand(
            requestStatus: DocumentStatusEnum::getProcessed(),
            userOfChange:  'MANAGER',
            comments:      null,
        );

        $this->invokePrivate('sendStatusNotifications', $certificate, $command);

        Notification::assertSentOnDemand(CertificateRequestStatusNotification::class);
    }

    public function test_processed_command_usa_mensaje_generado_no_el_comment_original(): void
    {
        Notification::fake();

        $certificate = $this->makeCertificate(uuid: 'TEST-UUID-001');
        $command     = $this->makeCommand(
            requestStatus: DocumentStatusEnum::getProcessed(),
            userOfChange:  'MANAGER',
            comments:      'Ignorado en PROCESSED',
        );

        $this->invokePrivate('sendStatusNotifications', $certificate, $command);

        Notification::assertSentOnDemand(
            CertificateRequestStatusNotification::class,
            function ($notification) {
                // En PROCESSED, los comments son generados internamente (no el original)
                return str_contains($notification->messageData->comments, 'TEST-UUID-001')
                    || str_contains((string) $notification->messageData->comments, 'procesada exitosamente');
            }
        );
    }

    public function test_rejected_command_usa_los_comments_originales(): void
    {
        Notification::fake();

        $comentario  = 'Falta firma del representante legal';
        $certificate = $this->makeCertificate();
        $command     = $this->makeCommand(
            requestStatus: DocumentStatusEnum::getRejected(),
            userOfChange:  'MANAGER',
            comments:      $comentario,
        );

        $this->invokePrivate('sendStatusNotifications', $certificate, $command);

        Notification::assertSentOnDemand(CertificateRequestStatusNotification::class);
    }

    // ── Enum de valores de estado ────────────────────────────────────────────

    public function test_documento_status_enum_rejected_es_string_correcto(): void
    {
        $this->assertSame('REJECTED', DocumentStatusEnum::getRejected());
    }

    public function test_documento_status_enum_processed_es_string_correcto(): void
    {
        $this->assertSame('PROCESSED', DocumentStatusEnum::getProcessed());
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    /** Crea un mock de CertificateRequest con los atributos necesarios para los tests. */
    private function makeCertificate(
        int    $id         = 1,
        int    $companyId  = 1,
        string $uuid       = 'CERT-0001',
    ): CertificateRequest {
        $company = (object) [
            'email' => 'empresa@test.com',
            'name'  => 'Empresa Test S.A.S',
        ];

        /** @var CertificateRequest&\Mockery\MockInterface $cert */
        $cert = Mockery::mock(CertificateRequest::class)->makePartial();
        $cert->id         = $id;
        $cert->company_id = $companyId;
        $cert->uuid       = $uuid;
        $cert->shouldReceive('getAttribute')->with('company')->andReturn($company);
        // Acceso directo a la propiedad "company" como magic getter
        $cert->company = $company;

        return $cert;
    }

    private function makeCommand(
        int     $certificateId = 1,
        int     $companyId     = 1,
        string  $requestStatus = 'PENDING',
        ?string $comments      = null,
        string  $userOfChange  = 'USER',
        int     $userId        = 1,
    ): UpdateCertificateStatusCommand {
        return new UpdateCertificateStatusCommand(
            certificateId: $certificateId,
            companyId:     $companyId,
            requestStatus: $requestStatus,
            comments:      $comments,
            userOfChange:  $userOfChange,
            userId:        $userId,
        );
    }

    /** Invoca el método privado sendStatusNotifications del handler. */
    private function invokePrivate(string $method, object $certificate, UpdateCertificateStatusCommand $command): void
    {
        $reflection = new ReflectionClass($this->handler);
        $m          = $reflection->getMethod($method);
        $m->setAccessible(true);
        $m->invoke($this->handler, $certificate, $command);
    }
}
