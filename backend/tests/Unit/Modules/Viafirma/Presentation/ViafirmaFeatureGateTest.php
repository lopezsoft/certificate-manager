<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Presentation;

use App\Modules\Viafirma\Presentation\Http\Middleware\ViafirmaFeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Tests del Feature Gate middleware (V-508).
 */
class ViafirmaFeatureGateTest extends TestCase
{
    /** @test */
    public function it_allows_request_when_enabled(): void
    {
        config(['viafirma.feature_flag.enabled' => true]);
        config(['viafirma.feature_flag.rollout_percentage' => 100]);

        $middleware = new ViafirmaFeatureGate();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function () {
            return new JsonResponse(['ok' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_blocks_request_when_disabled(): void
    {
        config(['viafirma.feature_flag.enabled' => false]);

        $middleware = new ViafirmaFeatureGate();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function () {
            return new JsonResponse(['ok' => true]);
        });

        $this->assertEquals(503, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('VIAFIRMA_DISABLED', $data['code']);
    }

    /** @test */
    public function it_applies_rollout_percentage(): void
    {
        config(['viafirma.feature_flag.enabled' => true]);
        config(['viafirma.feature_flag.rollout_percentage' => 0]); // 0% = nobody

        $middleware = new ViafirmaFeatureGate();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function () {
            return new JsonResponse(['ok' => true]);
        });

        // With 0% rollout, it should block
        $this->assertEquals(503, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('VIAFIRMA_ROLLOUT_PENDING', $data['code']);
    }

    /** @test */
    public function it_allows_all_at_100_percent_rollout(): void
    {
        config(['viafirma.feature_flag.enabled' => true]);
        config(['viafirma.feature_flag.rollout_percentage' => 100]);

        $middleware = new ViafirmaFeatureGate();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function () {
            return new JsonResponse(['ok' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
