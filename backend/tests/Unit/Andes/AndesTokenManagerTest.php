<?php

namespace Tests\Unit\Andes;

use App\Andes\Exceptions\AndesAuthenticationException;
use App\Andes\Services\AndesTokenManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests unitarios para AndesTokenManager.
 * Usa Http::fake() y Cache::fake().
 */
class AndesTokenManagerTest extends TestCase
{
    private const API_URL  = 'https://v2.andesid.com.co/api';
    private const USERNAME = 'test_user';
    private const PASSWORD = 'test_pass';
    private const TTL      = 3300;

    private AndesTokenManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new AndesTokenManager(
            apiUrl:   self::API_URL,
            username: self::USERNAME,
            password: self::PASSWORD,
            cacheTtl: self::TTL,
        );
    }

    public function test_obtiene_token_desde_api_y_lo_cachea(): void
    {
        Cache::flush();

        Http::fake([
            self::API_URL . '/login' => Http::response([
                'access_token' => 'token-from-api',
                'token_type'   => 'Bearer',
            ], 200),
        ]);

        $token = $this->manager->getValidToken();

        $this->assertSame('token-from-api', $token);

        // Segunda llamada — debe salir del caché sin llamar a la API
        Http::fake([]); // vacío → cualquier llamada HTTP fallaría
        $tokenCached = $this->manager->getValidToken();
        $this->assertSame('token-from-api', $tokenCached);
    }

    public function test_lanza_excepcion_si_login_falla(): void
    {
        Cache::flush();

        Http::fake([
            self::API_URL . '/login' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(AndesAuthenticationException::class);
        $this->expectExceptionMessage('HTTP 401');

        $this->manager->getValidToken();
    }

    public function test_lanza_excepcion_si_respuesta_no_trae_token(): void
    {
        Cache::flush();

        Http::fake([
            self::API_URL . '/login' => Http::response(['otro_campo' => 'value'], 200),
        ]);

        $this->expectException(AndesAuthenticationException::class);
        $this->expectExceptionMessage('access_token');

        $this->manager->getValidToken();
    }

    public function test_refresh_token_invalida_cache_y_renueva(): void
    {
        Cache::flush();

        Http::fake([
            self::API_URL . '/login' => Http::response(['access_token' => 'new-token'], 200),
        ]);

        // Pre-popular caché con token viejo
        Cache::put('andes_id_oauth_token', 'old-token', self::TTL);

        $token = $this->manager->refreshToken();

        $this->assertSame('new-token', $token);
        $this->assertNotSame('old-token', Cache::get('andes_id_oauth_token'));
    }
}

