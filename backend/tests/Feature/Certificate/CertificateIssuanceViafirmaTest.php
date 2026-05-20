<?php

declare(strict_types=1);

namespace Tests\Feature\Certificate;

use App\Models\CertificateRequest;
use App\Models\Company;
use App\Models\IdentityDocument;
use App\Models\TypeOrganization;
use App\Models\User;
use App\Modules\Viafirma\Application\UseCases\IssueCertificateUseCase;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests Feature E2E para el nuevo contrato unificado:
 *
 *   POST /api/v1/certificate-request/{id}/issue   (provider-agnostic)
 *
 * Validan la capa HTTP (rutas + middleware + FormRequest + factory de
 * proveedor + serialización) con el UseCase Viafirma mockeado para aislar
 * la presentación del dominio/infraestructura.
 *
 * El default del subsistema en este test se fija a 'viafirma' vía config
 * dinámico para no depender de variables de entorno.
 */
class CertificateIssuanceViafirmaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Forzamos viafirma como provider activo y la feature flag abierta.
        config()->set('certificate.issuance.default_provider', 'viafirma');
        config()->set('viafirma.feature_flag.enabled', true);
        config()->set('viafirma.feature_flag.rollout_percentage', 100);
        config()->set('viafirma.ra_code', 'viafirmaco-test');
    }

    private function endpoint(int $id): string
    {
        return "/api/v1/certificate-request/{$id}/issue";
    }

    // ── Auth ──────────────────────────────────────────────────────────────

    /** @test */
    public function issue_requires_authentication(): void
    {
        $this->postJson($this->endpoint(1), [
            'email_certificate' => 'test@example.com',
        ])->assertStatus(401);
    }

    // ── Validación ────────────────────────────────────────────────────────

    /** @test */
    public function issue_requires_email_when_provider_resolves_to_viafirma(): void
    {
        $user = $this->createAuthenticatedUser();
        $crId = $this->ensureCertificateRequestExists();

        // Sin email + hint=viafirma debe fallar la validación required_if.
        $response = $this->actingAs($user, 'api')
            ->postJson($this->endpoint($crId), [
                'provider' => 'viafirma',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email_certificate']);
    }

    /** @test */
    public function issue_validates_email_format(): void
    {
        $user = $this->createAuthenticatedUser();
        $crId = $this->ensureCertificateRequestExists();

        $this->actingAs($user, 'api')
            ->postJson($this->endpoint($crId), [
                'email_certificate' => 'not-an-email',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email_certificate']);
    }

    /** @test */
    public function issue_validates_organization_type_enum(): void
    {
        $user = $this->createAuthenticatedUser();
        $crId = $this->ensureCertificateRequestExists();

        $this->actingAs($user, 'api')
            ->postJson($this->endpoint($crId), [
                'email_certificate' => 'test@example.com',
                'organization_type' => 'INVALID_TYPE',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['organization_type']);
    }

    /** @test */
    public function issue_validates_identity_type_enum(): void
    {
        $user = $this->createAuthenticatedUser();
        $crId = $this->ensureCertificateRequestExists();

        $this->actingAs($user, 'api')
            ->postJson($this->endpoint($crId), [
                'email_certificate'      => 'test@example.com',
                'identity_type_override' => 'INVALID',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identity_type_override']);
    }

    // ── Errores de dominio (provider Viafirma) ────────────────────────────

    /** @test */
    public function issue_returns_409_for_duplicate_request(): void
    {
        $user = $this->createAuthenticatedUser();
        $crId = $this->ensureCertificateRequestExists();

        $this->mockUseCaseThrows(new ViafirmaException(
            "La solicitud {$crId} ya tiene un trámite Viafirma en curso (id=1, state=SUBMITTED)."
        ));

        $this->actingAs($user, 'api')
            ->postJson($this->endpoint($crId), [
                'email_certificate' => 'test@example.com',
                'organization_type' => 'RM',
            ])
            ->assertStatus(409)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function issue_returns_502_for_transient_viafirma_error(): void
    {
        $user = $this->createAuthenticatedUser();
        $crId = $this->ensureCertificateRequestExists();

        $this->mockUseCaseThrows(new TransientHttpException('Viafirma respondió 500'));

        $this->actingAs($user, 'api')
            ->postJson($this->endpoint($crId), [
                'email_certificate' => 'test@example.com',
                'organization_type' => 'RM',
            ])
            ->assertStatus(502)
            ->assertJson([
                'success' => false,
                'message' => 'Error de comunicación con Viafirma RA. Intente nuevamente.',
            ]);
    }

    // ── Éxito ─────────────────────────────────────────────────────────────

    /** @test */
    public function issue_returns_201_on_success(): void
    {
        $user = $this->createAuthenticatedUser();
        $crId = $this->ensureCertificateRequestExists();

        $entity = new ViafirmaCertificateRequest();
        $entity->id                     = 1;
        $entity->certificate_request_id = $crId;
        $entity->company_id             = 1;
        $entity->cod_request            = 'PYJR5N4QC';
        $entity->public_id              = 'bd6eda8d0f2d';
        $entity->internal_state         = InternalState::SUBMITTED;
        $entity->profile_type           = CertificateProfile::FE_PJ;
        $entity->identity_type          = IdentityType::IDC;
        $entity->country_code           = 'CO';
        $entity->ra_code                = 'viafirmaco';
        $entity->csr_fingerprint        = 'abc123';
        $entity->validity_days          = 730;
        $entity->poll_attempts          = 0;
        $entity->submitted_at           = now();
        $entity->expires_at             = now()->addHours(72);
        $entity->created_at             = now();
        $entity->updated_at             = now();

        $this->mockUseCaseReturns($entity);

        $response = $this->actingAs($user, 'api')
            ->postJson($this->endpoint($crId), [
                'email_certificate' => 'test@example.com',
                'organization_type' => 'RM',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Solicitud Viafirma creada exitosamente.',
            ])
            ->assertJsonPath('data.provider', 'viafirma')
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.external_id', 'PYJR5N4QC')
            ->assertJsonPath('data.data.internal_state', 'SUBMITTED');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function createAuthenticatedUser(): User
    {
        return User::factory()->create();
    }

    private function ensureCertificateRequestExists(): int
    {
        IdentityDocument::firstOrCreate(
            ['code' => '13'],
            ['document_name' => 'Cédula de Ciudadanía', 'abbreviation' => 'CC']
        );
        TypeOrganization::firstOrCreate(
            ['code' => 1],
            ['description' => 'Persona Jurídica']
        );

        $company = Company::factory()->create();
        $cr = CertificateRequest::create([
            'company_id'           => $company->id,
            'city_id'              => 1,
            'identity_document_id' => 1,
            'type_organization_id' => 1,
            'company_name'         => 'TEST SAS',
            'dni'                  => '900400300',
            'document_number'      => '1098765432',
            'address'              => 'Calle 123',
            'legal_representative' => 'Test User',
            'life'                 => 1,
            'request_status'       => 'DRAFT',
        ]);

        return $cr->id;
    }

    private function mockUseCaseThrows(\Throwable $exception): void
    {
        $mock = Mockery::mock(IssueCertificateUseCase::class);
        $mock->shouldReceive('handle')->andThrow($exception);
        $this->app->instance(IssueCertificateUseCase::class, $mock);
    }

    private function mockUseCaseReturns(ViafirmaCertificateRequest $entity): void
    {
        $mock = Mockery::mock(IssueCertificateUseCase::class);
        $mock->shouldReceive('handle')->andReturn($entity);
        $this->app->instance(IssueCertificateUseCase::class, $mock);
    }
}

