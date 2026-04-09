<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Tests unitarios para el middleware EnsureUserIsAdmin.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class EnsureUserIsAdminTest extends TestCase
{
    private EnsureUserIsAdmin $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureUserIsAdmin();
    }

    public function test_permite_acceso_a_usuario_admin(): void
    {
        $user = User::make(['type_id' => 1, 'email' => 'admin@test.com']);

        $request = Request::create('/api/v1/admin/test', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_bloquea_acceso_a_usuario_no_admin(): void
    {
        $user = User::make(['type_id' => 2, 'email' => 'user@test.com']);

        $request = Request::create('/api/v1/admin/test', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('administrador', $data['message']);
    }

    public function test_bloquea_acceso_a_usuario_no_autenticado(): void
    {
        $request = Request::create('/api/v1/admin/test', 'POST');
        $request->setUserResolver(fn () => null);

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
    }
}

