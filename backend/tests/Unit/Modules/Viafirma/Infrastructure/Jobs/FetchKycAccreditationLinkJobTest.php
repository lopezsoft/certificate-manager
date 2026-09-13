<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Application\Services\KycImmediateWebhookBatcher;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException;
use App\Modules\Viafirma\Infrastructure\Jobs\FetchKycAccreditationLinkJob;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\TestCase;
use Tests\Unit\Modules\Viafirma\CreatesViafirmaSchemaInMemory;

/**
 * El job hace `ViafirmaCertificateRequest::with([...])->find()`, consulta
 * estática que no admite dobles: las filas van a SQLite `:memory:` con el
 * esquema mínimo. El cliente Viafirma y el batcher de webhooks sí se mockean.
 */
final class FetchKycAccreditationLinkJobTest extends TestCase
{
    use CreatesViafirmaSchemaInMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createViafirmaRequestTables();

        // El job hace eager-load de `certificateRequest.company`.
        $this->createCertificateRequestsTable();
        $this->createCatalogTables();

        Notification::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function persistEntity(
        ?string $codRequest = 'TEST-COD-001',
        ?string $cachedLink = null,
    ): ViafirmaCertificateRequest {
        $entity = new ViafirmaCertificateRequest();
        $entity->certificate_request_id = 42;
        $entity->cod_request = $codRequest;
        $entity->public_id   = 'PUB-001';
        $entity->save();

        $state = new ViafirmaCertificateRequestState();
        $state->viafirma_certificate_request_id = $entity->id;
        $state->internal_state         = InternalState::POLLING;
        $state->remote_status          = RemoteStatus::ACCREDITATION->value;
        $state->kyc_accreditation_link = $cachedLink;
        $state->save();

        return $entity->fresh();
    }

    private function runJob(int $requestId, ViafirmaClient $client): void
    {
        (new FetchKycAccreditationLinkJob($requestId))->handle(
            $client,
            new SafePemLogger(new NullLogger()),
            Mockery::mock(KycImmediateWebhookBatcher::class)->shouldIgnoreMissing(),
        );
    }

    #[Test]
    public function persiste_link_en_exito(): void
    {
        $entity   = $this->persistEntity();
        $expected = 'https://kyc.viafirma.com/success?req=TEST-COD-001';

        $client = $this->createMock(ViafirmaClient::class);
        $client->expects($this->once())
            ->method('getAccreditationLink')
            ->with('TEST-COD-001', 'PUB-001')
            ->willReturn($expected);

        $this->runJob($entity->id, $client);

        $this->assertSame($expected, $entity->fresh()->state->kyc_accreditation_link);
    }

    /**
     * Idempotencia: con el link ya capturado no debe repetirse la llamada
     * HTTP ni reenviarse el aviso a la empresa.
     */
    #[Test]
    public function es_idempotente_si_link_ya_existe(): void
    {
        $cached = 'https://kyc.viafirma.com/cached';
        $entity = $this->persistEntity(cachedLink: $cached);

        $client = $this->createMock(ViafirmaClient::class);
        $client->expects($this->never())->method('getAccreditationLink');

        $this->runJob($entity->id, $client);

        $this->assertSame($cached, $entity->fresh()->state->kyc_accreditation_link);
    }

    /**
     * Un 4xx no se arregla reintentando: se registra y se sigue. El link queda
     * disponible on-demand mientras el remote_status sea 'accreditation'.
     */
    #[Test]
    public function no_relanza_error_cliente_no_transitorio(): void
    {
        $entity = $this->persistEntity();

        $client = $this->createMock(ViafirmaClient::class);
        $client->method('getAccreditationLink')
            ->willThrowException(new ViafirmaClientException('Bad request', 400));

        $this->runJob($entity->id, $client);

        $this->assertNull($entity->fresh()->state->kyc_accreditation_link);
    }

    /** Un error transitorio SÍ debe propagarse para que la cola reintente. */
    #[Test]
    public function relanza_error_transitorio_para_reintento(): void
    {
        $entity = $this->persistEntity();

        $client = $this->createMock(ViafirmaClient::class);
        $client->method('getAccreditationLink')
            ->willThrowException(new TransientHttpException('503 Service Unavailable', 503));

        $this->expectException(TransientHttpException::class);

        $this->runJob($entity->id, $client);
    }

    /** Una entidad inexistente se registra y se ignora, sin lanzar. */
    #[Test]
    public function maneja_entidad_no_encontrada_gracefully(): void
    {
        $client = $this->createMock(ViafirmaClient::class);
        $client->expects($this->never())->method('getAccreditationLink');

        $this->runJob(99999, $client);

        $this->assertTrue(true, 'No debe lanzar excepción.');
    }

    /** Sin cod_request no hay nada que consultar al proveedor. */
    #[Test]
    public function no_llama_al_cliente_sin_cod_request(): void
    {
        $entity = $this->persistEntity(codRequest: null);

        $client = $this->createMock(ViafirmaClient::class);
        $client->expects($this->never())->method('getAccreditationLink');

        $this->runJob($entity->id, $client);

        $this->assertNull($entity->fresh()->state->kyc_accreditation_link);
    }
}
