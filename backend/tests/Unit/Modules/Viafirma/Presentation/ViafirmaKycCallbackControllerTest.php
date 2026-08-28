<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Presentation;

use App\Modules\Viafirma\Application\UseCases\RecordKycFlowCompletedUseCase;
use App\Modules\Viafirma\Presentation\Http\Controllers\ViafirmaKycCallbackController;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Controller invocado directamente (sin kernel HTTP ni router) y con el
 * UseCase mockeado — no toca base de datos.
 */
final class ViafirmaKycCallbackControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function delega_al_use_case_con_ip_y_user_agent_y_redirige_al_destino_configurado(): void
    {
        config(['app.frontend_url' => 'https://app.example.com']);
        config(['viafirma.kyc.completed_path' => '/#/viafirma/verificacion-completada']);

        $useCase = Mockery::mock(RecordKycFlowCompletedUseCase::class);
        $useCase->shouldReceive('handle')
            ->once()
            ->with('PUB123', '10.0.0.1', 'TestAgent/1.0')
            ->andReturn(null);

        $request = Request::create('/api/v1/viafirma/kyc-callback/PUB123', 'GET', server: [
            'REMOTE_ADDR'     => '10.0.0.1',
            'HTTP_USER_AGENT' => 'TestAgent/1.0',
        ]);

        $controller = new ViafirmaKycCallbackController($useCase);
        $response   = $controller($request, 'PUB123');

        $this->assertSame(
            'https://app.example.com/#/viafirma/verificacion-completada',
            $response->getTargetUrl()
        );
    }

    #[Test]
    public function normaliza_barras_al_construir_el_destino_final(): void
    {
        // frontend_url con / final + completed_path sin / inicial no deben duplicar/perder la barra.
        config(['app.frontend_url' => 'https://app.example.com/']);
        config(['viafirma.kyc.completed_path' => 'viafirma/verificacion-completada']);

        $useCase = Mockery::mock(RecordKycFlowCompletedUseCase::class);
        $useCase->shouldReceive('handle')->once()->andReturn(null);

        $request    = Request::create('/api/v1/viafirma/kyc-callback/PUB123', 'GET');
        $controller = new ViafirmaKycCallbackController($useCase);
        $response   = $controller($request, 'PUB123');

        $this->assertSame(
            'https://app.example.com/viafirma/verificacion-completada',
            $response->getTargetUrl()
        );
    }
}
