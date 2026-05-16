<?php

declare(strict_types=1);

namespace Tests\Feature\Viafirma;

use App\Models\CertificateRequest;
use App\Models\Company;
use App\Models\IdentityDocument;
use App\Models\TypeOrganization;
use App\Models\User;
use App\Modules\Viafirma\Application\UseCases\IssueCertificateUseCase;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests Feature E2E para ViafirmaCertificateController (V-209).
 *
 * Estos tests validan la integración HTTP (rutas, middleware, FormRequest,
 * serialización de respuestas) con el UseCase mockeado para aislar la capa
 * de presentación del dominio/infraestructura.
 */
class ViafirmaCertificateControllerTest extends TestCase
{
    private const ISSUE_ENDPOINT = '/api/v2/certificates/viafirma/issue';
    private const INDEX_ENDPOINT = '/api/v2/certificates/viafirma';

    // ── POST /issue — Validación ─────────────────────────────────────────

    /** @test */
    public function issue_requires_authentication(): void
    {
        $response = $this->postJson(self::ISSUE_ENDPOINT, [
            'certificate_request_id' => 1,
            'email_certificate'      => 'test@example.com',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function issue_validates_required_fields(): void
    {
        $user = $this->createAuthenticatedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson(self::ISSUE_ENDPOINT, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'certificate_request_id',
                'email_certificate',
            ]);
    }

    /** @test */
    public function issue_validates_email_format(): void
    {
        $user = $this->createAuthenticatedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson(self::ISSUE_ENDPOINT, [
                'certificate_request_id' => 1,
                'email_certificate'      => 'not-an-email',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email_certificate']);
    }

    /** @test */
    public function issue_validates_organization_type_enum(): void
    {
        $user = $this->createAuthenticatedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson(self::ISSUE_ENDPOINT, [
                'certificate_request_id' => 1,
                'email_certificate'      => 'test@example.com',
                'organization_type'      => 'INVALID_TYPE',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['organization_type']);
    }

    /** @test */
    public function issue_validates_identity_type_enum(): void
    {
        $user = $this->createAuthenticatedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson(self::ISSUE_ENDPOINT, [
                'certificate_request_id' => 1,
                'email_certificate'      => 'test@example.com',
                'identity_type_override' => 'INVALID',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identity_type_override']);
    }

    // ── POST /issue — Domain Errors ──────────────────────────────────────

    /** @test */
    public function issue_returns_409_for_duplicate_request(): void
    {
        $user = $this->createAuthenticatedUser();

        $this->mockUseCaseThrows(new ViafirmaException(
            'La solicitud 42 ya tiene un trámite Viafirma en curso (id=1, state=SUBMITTED).'
        ));

        $response = $this->actingAs($user, 'api')
            ->postJson(self::ISSUE_ENDPOINT, [
                'certificate_request_id' => $this->ensureCertificateRequestExists(),
                'email_certificate'      => 'test@example.com',
                'organization_type'      => 'RM',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonFragment(['success' => false]);
    }

    /** @test */
    public function issue_returns_502_for_transient_viafirma_error(): void
    {
        $user = $this->createAuthenticatedUser();

        $this->mockUseCaseThrows(new TransientHttpException(
            'Viafirma respondió 500'
        ));

        $response = $this->actingAs($user, 'api')
            ->postJson(self::ISSUE_ENDPOINT, [
                'certificate_request_id' => $this->ensureCertificateRequestExists(),
                'email_certificate'      => 'test@example.com',
                'organization_type'      => 'RM',
            ]);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
                'message' => 'Error de comunicación con Viafirma RA. Intente nuevamente.',
            ]);
    }

    // ── POST /issue — Success ────────────────────────────────────────────

    /** @test */
    public function issue_returns_201_on_success(): void
    {
        $user = $this->createAuthenticatedUser();

        $entity = new ViafirmaCertificateRequest();
        $entity->id = 1;
        $entity->certificate_request_id = 42;
        $entity->company_id = 1;
        $entity->cod_request = 'PYJR5N4QC';
        $entity->public_id = 'bd6eda8d0f2d';
        $entity->internal_state = InternalState::SUBMITTED;
        $entity->profile_type = CertificateProfile::FE_PJ;
        $entity->identity_type = \App\Modules\Viafirma\Domain\Enums\IdentityType::IDC;
        $entity->country_code = 'CO';
        $entity->ra_code = 'viafirmaco';
        $entity->csr_fingerprint = 'abc123';
        $entity->validity_days = 730;
        $entity->poll_attempts = 0;
        $entity->created_at = now();
        $entity->updated_at = now();

        $this->mockUseCaseReturns($entity);

        $response = $this->actingAs($user, 'api')
            ->postJson(self::ISSUE_ENDPOINT, [
                'certificate_request_id' => $this->ensureCertificateRequestExists(),
                'email_certificate'      => 'test@example.com',
                'organization_type'      => 'RM',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Solicitud Viafirma creada exitosamente.',
            ])
            ->assertJsonPath('data.cod_request', 'PYJR5N4QC')
            ->assertJsonPath('data.internal_state', 'SUBMITTED');
    }

    // ── GET /certificates/viafirma — Index ───────────────────────────────

    /** @test */
    public function index_requires_authentication(): void
    {
        $this->getJson(self::INDEX_ENDPOINT)->assertStatus(401);
    }

    // ── GET /certificates/viafirma/{id} — Show ───────────────────────────

    /** @test */
    public function show_requires_authentication(): void
    {
        $this->getJson(self::INDEX_ENDPOINT . '/1')->assertStatus(401);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function createAuthenticatedUser(): User
    {
        return User::factory()->create();
    }

    private function ensureCertificateRequestExists(): int
    {
        // Asegurar catálogos
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
