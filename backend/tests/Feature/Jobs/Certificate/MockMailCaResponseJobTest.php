<?php

namespace Tests\Feature\Jobs\Certificate;

use App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract;
use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Enums\CertificateRequestStatusEnum;
use App\Events\CertificateStatusChanged;
use App\Jobs\Certificate\MockMailCaResponseJob;
use App\Models\FileManager;
use App\Services\Certificate\SelfSignedCertificateGenerator;
use App\Services\Certificates\CertificateStoragePathResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;
use Carbon\Carbon;

class MockMailCaResponseJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Simular entorno sandbox
        app()->detectEnvironment(function () {
            return 'sandbox';
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_mock_ca_response_job_with_mockery()
    {
        Event::fake([CertificateStatusChanged::class]);
        Storage::fake('local');
        config(['certificate.storage.disk' => 'local']);

        $crId = 99;
        
        // Mock genérico para la instancia devuelta por find()
        $mockCr = Mockery::mock();
        $mockCr->id = $crId;
        $mockCr->company_name = 'Test Company SA';
        $mockCr->dni = '123456789';
        $mockCr->dv = '1';
        $mockCr->type_organization_id = 1;
        $mockCr->request_status = CertificateRequestStatusEnum::PROCESSING->value;
        $mockCr->base_path = 'certificates/123456789';
        $mockCr->life = 2;
        $mockCr->company_id = 1;
        
        // Job actualizará estos campos
        $mockCr->shouldReceive('save')->once()->andReturn(true);

        // Interceptar query()->find() mediante alias
        $builderMock = Mockery::mock();
        $builderMock->shouldReceive('find')->with($crId)->andReturn($mockCr);
        
        // Usamos alias con nombre completo en string para no triggerear el autoload
        $mockCrClass = Mockery::mock('alias:App\Models\CertificateRequest');
        $mockCrClass->shouldReceive('query')->andReturn($builderMock);

        // Mock de FileManager
        $mockFileManager = Mockery::mock('alias:App\Models\FileManager');
        $mockFileManager->shouldReceive('updateOrCreate')->once()->andReturn(new FileManager());

        // CryptoServiceContract
        $cryptoMock = Mockery::mock(CryptoServiceContract::class);

        // KeyVault
        $vaultMock = Mockery::mock(KeyVault::class);
        $vaultMock->shouldReceive('store')->once()->andReturn('ulid_pin_ref');

        // PathResolver
        $pathResolverMock = Mockery::mock(CertificateStoragePathResolver::class);
        $pathResolverMock->shouldReceive('disk')->andReturn('local');

        // Mock para CertificateValidatorService (Maneja el parseo del P12 falso)
        $validatorMock = Mockery::mock('alias:App\Services\CertificateValidatorService');
        $validatorMock->shouldReceive('parseValidity')->once()->andReturn([
            'validFrom' => Carbon::now(),
            'validTo'   => Carbon::now()->addYears(2),
        ]);

        // Generador P12 mockeado para evitar openSSL calls
        $generatorMock = Mockery::mock(SelfSignedCertificateGenerator::class);
        $generatorMock->shouldReceive('generateP12')->once()->andReturn('fake_p12_binary');

        // Job
        $job = new MockMailCaResponseJob($crId);
        $job->handle($cryptoMock, $generatorMock, $vaultMock, $pathResolverMock);

        // Validar aserciones en el objeto mock
        $this->assertEquals(CertificateRequestStatusEnum::PROCESSED->value, $mockCr->request_status);
        $this->assertNotNull($mockCr->pin);
        $this->assertInstanceOf(Carbon::class, $mockCr->issued_at);
        $this->assertInstanceOf(Carbon::class, $mockCr->cert_valid_to);
        $this->assertInstanceOf(Carbon::class, $mockCr->expiration_date);

        // Validar que se lanzó el evento
        Event::assertDispatched(CertificateStatusChanged::class, function ($event) use ($crId) {
            return $event->certificateRequestId === $crId
                && $event->newStatus === CertificateRequestStatusEnum::PROCESSED->value;
        });
        
        // Validar archivo temporal
        $files = Storage::disk('local')->allFiles('certificates/123456789');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.zip', $files[0]);
    }
}