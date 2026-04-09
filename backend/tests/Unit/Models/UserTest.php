<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;

/**
 * Tests unitarios para el modelo User.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class UserTest extends TestCase
{
    public function test_is_admin_retorna_true_cuando_type_id_es_1(): void
    {
        $user = User::make(['type_id' => 1, 'email' => 'admin@test.com']);

        $this->assertTrue($user->is_admin);
    }

    public function test_is_admin_retorna_false_cuando_type_id_es_diferente_de_1(): void
    {
        $user = User::make(['type_id' => 2, 'email' => 'user@test.com']);

        $this->assertFalse($user->is_admin);
    }

    public function test_is_admin_retorna_false_cuando_type_id_es_null(): void
    {
        $user = User::make(['type_id' => null, 'email' => 'user@test.com']);

        $this->assertFalse($user->is_admin);
    }

    public function test_is_admin_esta_en_appends(): void
    {
        $user = User::make(['type_id' => 1, 'email' => 'admin@test.com']);
        $array = $user->toArray();

        $this->assertArrayHasKey('is_admin', $array);
    }

    public function test_name_accessor_concatena_nombre_y_apellido(): void
    {
        $user = User::make([
            'first_name' => 'Juan',
            'last_name'  => 'Pérez',
        ]);

        $this->assertEquals('Juan Pérez', $user->name);
    }
}

