<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Infrastructure;

use App\Modules\Viafirma\Application\Services\KycImmediateWebhookBatcher;
use App\Modules\Viafirma\Domain\Contracts\KycWebhookNotifierContract;
use App\Modules\Viafirma\Infrastructure\Jobs\FlushKycImmediateWebhookJob;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 100% mockeado — no toca base de datos ni hace peticiones HTTP reales.
 */
final class FlushKycImmediateWebhookJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function envia_el_webhook_con_tipo_inmediato_y_todo_lo_acumulado_en_el_buffer(): void
    {
        $solicitudes = [
            ['codigo' => 'ABC123', 'enlace' => 'https://viafirma.com/kyc/1'],
            ['codigo' => 'DEF456', 'enlace' => 'https://viafirma.com/kyc/2'],
        ];

        $batcher = Mockery::mock(KycImmediateWebhookBatcher::class);
        $batcher->shouldReceive('pullBuffer')->once()->with(555)->andReturn($solicitudes);

        $notifier = Mockery::mock(KycWebhookNotifierContract::class);
        $notifier->shouldReceive('notify')
            ->once()
            ->with('ACME SAS', '3001234567', $solicitudes, 'inmediato');

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('info')->once()->with('viafirma.kyc_webhook_flush.done', [
            'company_id' => 555,
            'count'      => 2,
        ]);

        $job = new FlushKycImmediateWebhookJob(555, 'ACME SAS', '3001234567');
        $job->handle($batcher, $notifier, $logger);

        $this->assertTrue(true);
    }

    #[Test]
    public function no_llama_al_webhook_si_el_buffer_ya_esta_vacio(): void
    {
        $batcher = Mockery::mock(KycImmediateWebhookBatcher::class);
        $batcher->shouldReceive('pullBuffer')->once()->with(777)->andReturn([]);

        $notifier = Mockery::mock(KycWebhookNotifierContract::class);
        $notifier->shouldNotReceive('notify');

        $logger = Mockery::mock(SafePemLogger::class);
        $logger->shouldReceive('info')->once()->with('viafirma.kyc_webhook_flush.empty_buffer', [
            'company_id' => 777,
        ]);

        $job = new FlushKycImmediateWebhookJob(777, 'EMPRESA', null);
        $job->handle($batcher, $notifier, $logger);

        $this->assertTrue(true);
    }
}
