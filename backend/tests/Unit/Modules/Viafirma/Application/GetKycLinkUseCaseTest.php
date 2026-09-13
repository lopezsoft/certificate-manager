<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Application;

use App\Modules\Viafirma\Application\UseCases\GetKycLinkUseCase;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\TestCase;
use Tests\Unit\Modules\Viafirma\CreatesViafirmaSchemaInMemory;

/**
 * El use case hace `ViafirmaCertificateRequest::with('state')->findOrFail()`,
 * una consulta estática que no admite dobles, así que las filas se insertan en
 * SQLite `:memory:` con el esquema mínimo. Ninguna base real se toca.
 */
final class GetKycLinkUseCaseTest extends TestCase
{
    use CreatesViafirmaSchemaInMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createViafirmaRequestTables();
    }

    private function makeUseCase(?ViafirmaClient $client = null): GetKycLinkUseCase
    {
        return new GetKycLinkUseCase(
            client: $client ?? $this->createMock(ViafirmaClient::class),
            logger: new SafePemLogger(new NullLogger()),
        );
    }

    /**
     * Persiste entidad + estado y devuelve la entidad.
     */
    private function persistEntity(
        RemoteStatus $remoteStatus,
        ?string      $codRequest = 'TEST-COD-001',
        ?string      $cachedLink = null,
        InternalState $internalState = InternalState::POLLING,
    ): ViafirmaCertificateRequest {
        $entity = new ViafirmaCertificateRequest();
        $entity->certificate_request_id = 42;
        $entity->cod_request = $codRequest;
        $entity->public_id   = 'PUB-001';
        $entity->save();

        $state = new ViafirmaCertificateRequestState();
        $state->viafirma_certificate_request_id = $entity->id;
        $state->internal_state         = $internalState;
        $state->remote_status          = $remoteStatus->value;
        $state->kyc_accreditation_link = $cachedLink;
        $state->save();

        return $entity->fresh();
    }

    #[Test]
    public function retorna_link_cacheado_sin_llamar_cliente(): void
    {
        $cached = 'https://kyc.viafirma.com/accreditation/cached';

        $entity = $this->persistEntity(RemoteStatus::ACCREDITATION, cachedLink: $cached);

        // Si el link ya está capturado no debe hacerse llamada HTTP: permite
        // servirlo aunque Viafirma ya haya avanzado más allá de 'accreditation'.
        $client = $this->createMock(ViafirmaClient::class);
        $client->expects($this->never())->method('getAccreditationLink');

        $this->assertSame($cached, $this->makeUseCase($client)->handle($entity->id));
    }

    #[Test]
    public function lanza_excepcion_con_estado_real_cuando_no_es_accreditation(): void
    {
        $entity = $this->persistEntity(
            RemoteStatus::RUES_CHECK,
            codRequest: 'TEST-COD-002',
            internalState: InternalState::SUBMITTED,
        );

        $this->expectException(ViafirmaException::class);
        $this->expectExceptionMessageMatches('/rues_check/');

        $this->makeUseCase()->handle($entity->id);
    }

    #[Test]
    public function lanza_excepcion_cuando_no_hay_cod_request(): void
    {
        $entity = $this->persistEntity(RemoteStatus::ACCREDITATION, codRequest: null);

        $this->expectException(ViafirmaException::class);
        $this->expectExceptionMessageMatches('/cod_request/');

        $this->makeUseCase()->handle($entity->id);
    }

    #[Test]
    public function obtiene_y_persiste_link_en_vivo_cuando_no_esta_cacheado(): void
    {
        $entity   = $this->persistEntity(RemoteStatus::ACCREDITATION, codRequest: 'TEST-COD-003');
        $expected = 'https://kyc.viafirma.com/accreditation/live';

        $client = $this->createMock(ViafirmaClient::class);
        $client->expects($this->once())
            ->method('getAccreditationLink')
            ->with('TEST-COD-003', 'PUB-001')
            ->willReturn($expected);

        $link = $this->makeUseCase($client)->handle($entity->id);

        $this->assertSame($expected, $link);

        // Debe quedar cacheado para no repetir la llamada HTTP.
        $this->assertSame(
            $expected,
            $entity->fresh()->state->kyc_accreditation_link,
        );
    }

    #[Test]
    public function lanza_excepcion_cuando_entidad_no_existe(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->makeUseCase()->handle(99999);
    }
}
