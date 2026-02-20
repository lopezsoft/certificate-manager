<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Tests de característica para TokenController (Personal Access Tokens).
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class TokenControllerTest extends TestCase
{
    // ── Protección de autenticación ──────────────────────────────────────────

    public function test_get_tokens_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->getJson('/api/v1/tokens');

        $response->assertStatus(401);
    }

    public function test_post_tokens_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->postJson('/api/v1/tokens', ['name' => 'mi-token']);

        $response->assertStatus(401);
    }

    public function test_delete_token_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->deleteJson('/api/v1/tokens/fake-uuid');

        $response->assertStatus(401);
    }

    public function test_revoke_all_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->postJson('/api/v1/tokens/revoke-all');

        $response->assertStatus(401);
    }

    // ── Validación de entrada (store) ────────────────────────────────────────

    public function test_store_devuelve_422_si_name_no_se_provee(): void
    {
        /** @var User $user */
        $user = User::make(['id' => 1, 'email' => 'user@test.com']);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/tokens', []);

        // 422 Unprocessable Entity — validación de CreateTokenRequest
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_devuelve_422_si_name_supera_255_caracteres(): void
    {
        /** @var User $user */
        $user = User::make(['id' => 1, 'email' => 'user@test.com']);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/tokens', [
                'name' => str_repeat('a', 256),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_devuelve_422_si_expires_in_days_es_cero(): void
    {
        /** @var User $user */
        $user = User::make(['id' => 1, 'email' => 'user@test.com']);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/tokens', [
                'name'            => 'Token válido',
                'expires_in_days' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expires_in_days']);
    }

    public function test_store_devuelve_422_si_expires_in_days_supera_365(): void
    {
        /** @var User $user */
        $user = User::make(['id' => 1, 'email' => 'user@test.com']);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/tokens', [
                'name'            => 'Token válido',
                'expires_in_days' => 366,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expires_in_days']);
    }
}
