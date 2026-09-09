<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Application;

use App\Modules\Viafirma\Application\Services\KycImmediateWebhookBatcher;
use App\Modules\Viafirma\Infrastructure\Jobs\FlushKycImmediateWebhookJob;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * No toca base de datos — usa el driver de Cache configurado para tests
 * (array/local) y Queue::fake() para no ejecutar jobs reales.
 */
final class KycImmediateWebhookBatcherTest extends TestCase
{
    #[Test]
    public function agrupa_varios_enqueue_de_la_misma_empresa_en_un_solo_flush_programado(): void
    {
        Queue::fake();

        $batcher = new KycImmediateWebhookBatcher();
        $batcher->enqueue(999, 'EMPRESA TEST', '3001234567', 'COD1', 'https://link1');
        $batcher->enqueue(999, 'EMPRESA TEST', '3001234567', 'COD2', 'https://link2');
        $batcher->enqueue(999, 'EMPRESA TEST', '3001234567', 'COD3', 'https://link3');

        Queue::assertPushed(FlushKycImmediateWebhookJob::class, 1);

        $buffer = $batcher->pullBuffer(999);
        $this->assertCount(3, $buffer);
        $this->assertSame(
            [
                ['codigo' => 'COD1', 'enlace' => 'https://link1'],
                ['codigo' => 'COD2', 'enlace' => 'https://link2'],
                ['codigo' => 'COD3', 'enlace' => 'https://link3'],
            ],
            $buffer
        );
    }

    #[Test]
    public function pullBuffer_vacia_el_buffer_y_el_lock(): void
    {
        Queue::fake();

        $batcher = new KycImmediateWebhookBatcher();
        $batcher->enqueue(111, 'EMPRESA X', '3001234567', 'COD', 'https://link');

        $first  = $batcher->pullBuffer(111);
        $second = $batcher->pullBuffer(111);

        $this->assertCount(1, $first);
        $this->assertSame([], $second);
    }

    #[Test]
    public function empresas_distintas_no_comparten_buffer_ni_lock(): void
    {
        Queue::fake();

        $batcher = new KycImmediateWebhookBatcher();
        $batcher->enqueue(1, 'EMPRESA A', '3001111111', 'A1', 'https://a1');
        $batcher->enqueue(2, 'EMPRESA B', '3002222222', 'B1', 'https://b1');

        Queue::assertPushed(FlushKycImmediateWebhookJob::class, 2);

        $this->assertSame([['codigo' => 'A1', 'enlace' => 'https://a1']], $batcher->pullBuffer(1));
        $this->assertSame([['codigo' => 'B1', 'enlace' => 'https://b1']], $batcher->pullBuffer(2));
    }

    #[Test]
    public function despues_de_un_pullBuffer_un_nuevo_enqueue_programa_otro_flush(): void
    {
        Queue::fake();

        $batcher = new KycImmediateWebhookBatcher();
        $batcher->enqueue(42, 'EMPRESA', '3001234567', 'COD1', 'https://link1');
        $batcher->pullBuffer(42);

        $batcher->enqueue(42, 'EMPRESA', '3001234567', 'COD2', 'https://link2');

        Queue::assertPushed(FlushKycImmediateWebhookJob::class, 2);
    }
}
