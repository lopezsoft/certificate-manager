<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Application;

use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use App\Models\Company;
use App\Models\IdentityDocument;
use App\Models\TypeOrganization;
use App\Modules\Viafirma\Application\Commands\IssueCertificateCommand;
use App\Modules\Viafirma\Application\DTOs\CsrResult;
use App\Modules\Viafirma\Application\DTOs\KeyPair;
use App\Modules\Viafirma\Application\DTOs\SubmitCsrResultDto;
use App\Modules\Viafirma\Application\UseCases\IssueCertificateUseCase;
use App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract;
use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Domain\Mappers\IdentityTypeMapper;
use App\Modules\Viafirma\Domain\Mappers\ProfileTypeMapper;
use App\Modules\Viafirma\Infrastructure\Crypto\CsrBuilderFactory;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Tests unitarios del IssueCertificateUseCase.
 *
 * Escenarios cubiertos:
 *  1) PJ camino feliz → entity con cod_request, internal_state = SUBMITTED
 *  2) PN camino feliz → entity sin organization_type
 *  3) Solicitud duplicada → ViafirmaException
 *  4) PJ sin organizationType → ViafirmaException
 *  5) PN con organizationType → ViafirmaException
 *  6) 5xx transient → TransientHttpException propagada
 *  7) 4xx client → ViafirmaClientException propagada
 *  8) Llave huérfana limpiada en fallo
 *
 * Nota: el endpoint GET /ra/available-profiles NO se llama en el flujo de emisión.
 * Los valores ra_code y cod_profile se leen de config (VIAFIRMA_RA_CODE / VIAFIRMA_COD_PROFILE).
 */
class IssueCertificateUseCaseTest extends TestCase
{
    private MockInterface $crypto;
    private MockInterface $csrBuilderFactory;
    private MockInterface $keyVault;
    private MockInterface $viafirmaClient;
    private MockInterface $repository;
    private IssueCertificateUseCase $useCase;

    use \Tests\Unit\Modules\Viafirma\CreatesViafirmaSchemaInMemory;

    protected function setUp(): void
    {
        parent::setUp();

        // IssueCertificateUseCase hace CertificateRequest::query()->findOrFail(),
        // una llamada estática no interceptable. Se crea el esquema mínimo en
        // SQLite :memory: — ninguna base real se toca.
        $this->createCertificateRequestsTable();
        $this->createCatalogTables();

        $this->crypto            = Mockery::mock(CryptoServiceContract::class);
        $this->csrBuilderFactory = Mockery::mock(CsrBuilderFactory::class);
        $this->keyVault          = Mockery::mock(KeyVault::class);
        $this->viafirmaClient    = Mockery::mock(ViafirmaClient::class);
        $this->repository        = Mockery::mock(ViafirmaCertificateRequestRepositoryContract::class);

        // ra_code y cod_profile provienen de config (.env) — no del API remoto.
        config(['viafirma.ra_code'     => 'viafirmaco']);
        config(['viafirma.cod_profile' => 'VklBRklSTUEtQ08tUEotRkFDVFVSQUUtUDEy']);

        $this->useCase = new IssueCertificateUseCase(
            crypto:             $this->crypto,
            csrBuilderFactory:  $this->csrBuilderFactory,
            keyVault:           $this->keyVault,
            client:             $this->viafirmaClient,
            repository:         $this->repository,
            identityTypeMapper: new IdentityTypeMapper(),
            profileTypeMapper:  new ProfileTypeMapper(),
            logger:             new \App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger(new NullLogger()),
        );
    }

    // ── HELPERS ──────────────────────────────────────────────────────────────

    private function makeCertificateRequest(int $orgTypeCode = 1): CertificateRequest
    {
        // El use case hace CertificateRequest::query()->with([...])->findOrFail():
        // una llamada estática con eager-load, que no admite dobles y además
        // descarta cualquier relación puesta con setRelation(). Por eso las
        // filas se insertan en SQLite :memory: (esquema mínimo creado en
        // setUp) — ninguna base real se toca.
        \DB::table('countries')->insertOrIgnore([
            'id' => 45, 'name_country' => 'COLOMBIA', 'abbreviation_A2' => 'CO', 'abbreviation_A3' => 'COL',
        ]);
        \DB::table('departments')->insertOrIgnore([
            'id' => 5, 'country_id' => 45, 'name_department' => 'ANTIOQUIA',
        ]);
        \DB::table('cities')->insertOrIgnore([
            'id' => 1, 'department_id' => 5, 'name_city' => 'MEDELLÍN',
        ]);
        \DB::table('identity_documents')->insertOrIgnore([
            'id' => 1, 'code' => '13', 'abbreviation' => 'CC', 'description' => 'Cédula de Ciudadanía',
        ]);
        \DB::table('type_organization')->insertOrIgnore([
            'id'          => $orgTypeCode,
            'code'        => (string) $orgTypeCode,
            'description' => $orgTypeCode === 1 ? 'Persona Jurídica' : 'Persona Natural',
        ]);
        \DB::table('companies')->insertOrIgnore([
            'id'           => 1,
            'country_id'   => 45,
            'city_id'      => 1,
            'company_name' => 'MI COMPAÑÍA SAS',
            'trade_name'   => 'FACTURACIÓN',
            'dni'          => '900400300',
            'address'      => 'Carrera 65 #3',
            'email'        => 'info@empresa.com',
            'active'       => 1,
        ]);

        $cr = new CertificateRequest();
        $cr->id                   = 42;
        $cr->company_id           = 1;
        $cr->country_id           = 45;
        $cr->city_id              = 1;
        $cr->identity_document_id = 1;
        $cr->type_organization_id = $orgTypeCode;
        $cr->document_number      = '1098765432';
        $cr->legal_representative = 'Paula Ibarra';
        $cr->address              = 'Carrera 65 #3';
        $cr->dni                  = '900400300';
        $cr->email                = 'info@empresa.com';
        $cr->company_name         = 'MI COMPAÑÍA SAS';
        $cr->save();

        return $cr->fresh();
    }

    private function setupStandardMocks(CertificateRequest $cr, CertificateProfile $profile): void
    {
        // La solicitud ya existe en SQLite :memory' (la insertó
        // makeCertificateRequest), así que findOrFail() la encuentra sin
        // necesidad de doblar la llamada estática.

        // No existing Viafirma request
        $this->repository->shouldReceive('findByCertificateRequestId')
            ->with($cr->id)
            ->andReturn(null);

        // ra_code y cod_profile vienen de config — getProfiles() NO se llama en el flujo de emisión
        // (se llama manualmente UNA VEZ para obtener los valores que se guardan en .env)

        // Key pair
        $this->crypto->shouldReceive('generateKeyPair')
            ->andReturn(new KeyPair(
                publicKeyPem: '-----BEGIN PUBLIC KEY-----...',
                privateKeyPem: '-----BEGIN PRIVATE KEY-----...',
                bits: 2048,
            ));

        // CSR builder
        $csrBuilderMock = Mockery::mock(\App\Modules\Viafirma\Domain\Contracts\CsrBuilderStrategy::class);
        $csrBuilderMock->shouldReceive('build')
            ->andReturn(new CsrResult(
                pem: "-----BEGIN CERTIFICATE REQUEST-----\nMIIBx...\n-----END CERTIFICATE REQUEST-----\n",
                base64: base64_encode("-----BEGIN CERTIFICATE REQUEST-----\nMIIBx...\n-----END CERTIFICATE REQUEST-----\n"),
                fingerprint: 'abcdef1234567890',
            ));
        $this->csrBuilderFactory->shouldReceive('for')
            ->with($profile)
            ->andReturn($csrBuilderMock);

        // KeyVault
        $this->keyVault->shouldReceive('store')
            ->andReturn('vault://ref/42');

        // Submit CSR — el payload debe incluir `csr` como PEM completo en base64
        $this->viafirmaClient->shouldReceive('submitCsr')
            ->andReturn(new SubmitCsrResultDto(
                codRequest: 'PYJR5N4QC',
                publicId: 'bd6eda8d0f2d',
                initialStatus: 'rues_check',
                raw: ['codRequest' => 'PYJR5N4QC', 'publicId' => 'bd6eda8d0f2d'],
            ));

        // Repository create (returns a fresh entity)
        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;
        $entity->certificate_request_id = $cr->id;
        $entity->company_id = $cr->company_id;
        $entity->cod_request = 'PYJR5N4QC';
        $entity->public_id = 'bd6eda8d0f2d';
        $entity->internal_state = InternalState::SUBMITTED;


        $this->repository->shouldReceive('create')
            ->andReturn($entity);

        // Fresh (returns self for simplicity)
        $entity->setRelation('certificateRequest', $cr);
    }

    // ── TESTS ────────────────────────────────────────────────────────────────

    /** @test */
    public function it_rejects_pj_without_organization_type(): void
    {
        $this->expectException(ViafirmaException::class);
        $this->expectExceptionMessage('organizationType es obligatorio para perfiles FE-PJ');

        $cr = $this->makeCertificateRequest(1); // PJ
        $this->repository->shouldReceive('findByCertificateRequestId')
            ->with(42)
            ->andReturn(null);

        $command = new IssueCertificateCommand(
            certificateRequestId: 42,
            requestedByUserId:    1,
            emailCertificate:     'test@example.com',
            organizationType:     null, // missing for PJ → must fail
        );

        $this->useCase->handle($command);
    }

    /** @test */
    public function it_rejects_pn_with_organization_type(): void
    {
        $this->expectException(ViafirmaException::class);
        $this->expectExceptionMessage('organizationType NO debe enviarse para perfiles FE-PN');

        $cr = $this->makeCertificateRequest(2); // PN
        $this->repository->shouldReceive('findByCertificateRequestId')
            ->with(42)
            ->andReturn(null);

        $command = new IssueCertificateCommand(
            certificateRequestId: 42,
            requestedByUserId:    1,
            emailCertificate:     'test@example.com',
            organizationType:     OrganizationType::RM, // must not be sent for PN
        );

        $this->useCase->handle($command);
    }

    /** @test */
    public function it_rejects_duplicate_non_failed_request(): void
    {
        $this->expectException(ViafirmaException::class);
        $this->expectExceptionMessage('ya tiene un trámite Viafirma en curso');

        $cr = $this->makeCertificateRequest(1);

        // Existing non-failed request. internal_state vive en la tabla de
        // estados normalizada: se monta la relación para que isFailed() la lea
        // sin consultar BD.
        $existing = new ViafirmaCertificateRequest();
        $existing->id = 99;

        $existingState = new \App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState();
        $existingState->internal_state = InternalState::SUBMITTED;
        $existing->setRelation('state', $existingState);

        $this->repository->shouldReceive('findByCertificateRequestId')
            ->with(42)
            ->andReturn($existing);

        $command = new IssueCertificateCommand(
            certificateRequestId: 42,
            requestedByUserId:    1,
            emailCertificate:     'test@example.com',
            organizationType:     OrganizationType::RM,
        );

        $this->useCase->handle($command);
    }

    /** @test */
    public function it_cleans_orphan_key_on_submit_failure(): void
    {
        $cr = $this->makeCertificateRequest(1);
        $this->repository->shouldReceive('findByCertificateRequestId')
            ->with(42)
            ->andReturn(null);

        // ra_code y cod_profile vienen de config — NO de getProfiles()
        $this->crypto->shouldReceive('generateKeyPair')
            ->andReturn(new KeyPair(
                publicKeyPem: '-----BEGIN PUBLIC KEY-----...',
                privateKeyPem: '-----BEGIN PRIVATE KEY-----...',
                bits: 2048,
            ));

        $csrBuilderMock = Mockery::mock(\App\Modules\Viafirma\Domain\Contracts\CsrBuilderStrategy::class);
        $csrBuilderMock->shouldReceive('build')
            ->andReturn(new CsrResult(
                pem: "-----BEGIN CERTIFICATE REQUEST-----\nMIIBx...\n-----END CERTIFICATE REQUEST-----\n",
                base64: base64_encode("-----BEGIN CERTIFICATE REQUEST-----\nMIIBx...\n-----END CERTIFICATE REQUEST-----\n"),
                fingerprint: 'abcdef1234567890',
            ));
        $this->csrBuilderFactory->shouldReceive('for')
            ->andReturn($csrBuilderMock);

        $this->keyVault->shouldReceive('store')
            ->andReturn('vault://ref/42');

        // Submit throws TransientHttpException
        $this->viafirmaClient->shouldReceive('submitCsr')
            ->andThrow(new TransientHttpException('Viafirma respondió 500'));

        // Key must be cleaned up
        $this->keyVault->shouldReceive('destroy')
            ->with('vault://ref/42')
            ->once();

        $this->expectException(TransientHttpException::class);

        $command = new IssueCertificateCommand(
            certificateRequestId: 42,
            requestedByUserId:    1,
            emailCertificate:     'test@example.com',
            organizationType:     OrganizationType::RM,
        );

        $this->useCase->handle($command);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
