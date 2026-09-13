<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ejercita POST /api/v1/sync-account de punta a punta: middleware HMAC
 * (ValidateSyncSignature) + handler de upsert (RegisteredUserController@syncAccount).
 *
 * El esquema se crea en SQLite `:memory:` porque las tablas involucradas
 * (users, companies, business_users, user_types y los catálogos que Company
 * eager-loadea) son schema legacy sin migración en el repo.
 *
 * Cada caso corre en su propio proceso: `routes/api.php` carga el grupo v1 con
 * `require_once`, así que al recrear la aplicación en el mismo proceso PHP las
 * rutas de v1 no se vuelven a registrar y todo responde 404 salvo el primer
 * test. Aislar el proceso es la forma de probar estas rutas sin tocar
 * `routes/api.php`.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SyncAccountTest extends TestCase
{
    private const KEY    = 'cm-sync-test-key';
    private const SECRET = 'test-secret-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sync.api_key'     => self::KEY,
            'services.sync.api_secret'  => self::SECRET,
            'services.sync.allowed_ips' => [],
        ]);

        $this->createSchema();
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function createSchema(): void
    {
        // SQLite `:memory:` se comparte dentro del proceso: se tira y recrea
        // en cada test para no arrastrar filas entre casos. No se usa
        // RefreshDatabase ni migraciones, y no se toca ninguna BD real.
        foreach ([
            'business_users', 'general_setting_companies', 'companies', 'users',
            'user_types', 'countries', 'cities', 'identity_documents', 'type_organization',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('user_types', function ($t) {
            $t->id();
            $t->string('user_type_name')->nullable();
            $t->string('type')->nullable();
            $t->integer('active')->default(1);
        });

        Schema::create('users', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('type_id')->nullable();
            $t->string('avatar')->nullable();
            $t->integer('active')->default(1);
            $t->string('remember_token')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('companies', function ($t) {
            $t->id();
            $t->unsignedBigInteger('country_id')->nullable();
            $t->unsignedBigInteger('city_id')->nullable();
            $t->unsignedBigInteger('identity_document_id')->nullable();
            $t->unsignedBigInteger('type_organization_id')->nullable();
            $t->string('company_name')->nullable();
            $t->string('dni')->nullable();
            $t->string('dv')->nullable();
            $t->string('address')->nullable();
            $t->string('city_name')->nullable();
            $t->string('location')->nullable();
            $t->string('postal_code')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('image')->nullable();
            $t->integer('active')->default(1);
            $t->string('uuid')->nullable();
            $t->string('issuance_provider')->nullable();
        });

        Schema::create('business_users', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('company_id');
        });

        // Catálogos que Company eager-loadea vía $with. Los nombres de tabla
        // salen de los modelos (nótese `type_organization`, en singular).
        foreach (['countries', 'cities', 'identity_documents'] as $table) {
            Schema::create($table, function ($t) {
                $t->id();
                $t->string('name')->nullable();
                $t->string('code')->nullable();
                $t->integer('active')->default(1);
            });
        }

        Schema::create('type_organization', function ($t) {
            $t->id();
            $t->string('description')->nullable();
            $t->string('code')->nullable();
            $t->integer('active')->default(1);
        });

        Schema::create('general_setting_companies', function ($t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->unsignedBigInteger('setting_id')->nullable();
        });

        DB::table('user_types')->insert([
            ['id' => 1, 'user_type_name' => 'Administrador', 'type' => 'admin', 'active' => 1],
            ['id' => 2, 'user_type_name' => 'Casa de Software', 'type' => 'software', 'active' => 1],
            ['id' => 3, 'user_type_name' => 'Arrendamiento en Servidor', 'type' => 'hosting', 'active' => 1],
            ['id' => 4, 'user_type_name' => 'Partner', 'type' => 'partner', 'active' => 1],
        ]);

        DB::table('cities')->insert(['id' => 149, 'name' => 'Barranquilla', 'active' => 1]);
        DB::table('countries')->insert(['id' => 45, 'name' => 'Colombia', 'active' => 1]);
        DB::table('identity_documents')->insert(['id' => 3, 'name' => 'NIT', 'code' => '31', 'active' => 1]);
        DB::table('type_organization')->insert(['id' => 1, 'description' => 'Juridica', 'code' => '1', 'active' => 1]);
    }

    private function payload(array $overrides = []): array
    {
        $base = [
            'user' => [
                'email'         => 'dev@matias.com.co',
                'password_hash' => Hash::make('secret-del-erp'),
                'first_name'    => 'Juan',
                'last_name'     => 'Perez',
                'type_id'       => 3,
            ],
            'company' => [
                'company_name' => 'Matias ERP S.A.S.',
                'dni'          => '900455420',
                'dv'           => '1',
                'email'        => 'facturacion@matias.com.co',
                'phone'        => '3001234567',
                'address'      => 'Calle 1 # 2-3',
                'city_id'      => 149,
                'country_id'   => 45,
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }

    /** Firma y envía igual que lo haría el servicio del ERP. */
    private function sync(array $payload, array $headerOverrides = [])
    {
        $body      = json_encode($payload);
        $timestamp = (string) time();

        $headers = array_merge([
            'Content-Type'     => 'application/json',
            'Accept'           => 'application/json',
            'X-Sync-Key'       => self::KEY,
            'X-Sync-Timestamp' => $timestamp,
            'X-Sync-Signature' => hash_hmac('sha256', $body . $timestamp, self::SECRET),
        ], $headerOverrides);

        // El body va crudo (no como array) porque la firma HMAC se calcula sobre
        // `$request->getContent()`: cualquier re-serialización la invalidaría.
        // Los headers van en $server —no vía withHeaders()— porque al pasar
        // $server explícito a call() los de withHeaders() se descartan.
        return $this->call(
            'POST',
            '/api/v1/sync-account',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $body
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Middleware HMAC
    // ─────────────────────────────────────────────────────────────────

    public function test_rechaza_peticion_sin_headers(): void
    {
        $body = json_encode($this->payload());

        $response = $this->call(
            'POST',
            '/api/v1/sync-account',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ]),
            $body
        );

        $response->assertStatus(401);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rechaza_api_key_invalida(): void
    {
        $this->sync($this->payload(), ['X-Sync-Key' => 'key-equivocada'])
            ->assertStatus(401)
            ->assertJson(['error' => 'API Key inválida']);
    }

    public function test_rechaza_firma_invalida(): void
    {
        $this->sync($this->payload(), ['X-Sync-Signature' => str_repeat('a', 64)])
            ->assertStatus(401)
            ->assertJson(['error' => 'Firma inválida']);
    }

    public function test_rechaza_timestamp_expirado(): void
    {
        $payload   = $this->payload();
        $body      = json_encode($payload);
        $timestamp = (string) (time() - 600); // fuera de la ventana de 300s

        $this->call(
            'POST',
            '/api/v1/sync-account',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Accept'           => 'application/json',
                'Content-Type'     => 'application/json',
                'X-Sync-Key'       => self::KEY,
                'X-Sync-Timestamp' => $timestamp,
                'X-Sync-Signature' => hash_hmac('sha256', $body . $timestamp, self::SECRET),
            ]),
            $body
        )->assertStatus(401)->assertJson(['error' => 'Request expirado']);
    }

    public function test_rechaza_ip_no_autorizada(): void
    {
        config(['services.sync.allowed_ips' => ['203.0.113.9']]);

        $this->sync($this->payload())->assertStatus(403);
    }

    public function test_rechaza_si_el_secret_no_esta_configurado(): void
    {
        config(['services.sync.api_key' => null]);

        $this->sync($this->payload())->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────
    // Happy path: creación
    // ─────────────────────────────────────────────────────────────────

    public function test_crea_cuenta_nueva_y_devuelve_201(): void
    {
        $response = $this->sync($this->payload());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Cuenta sincronizada exitosamente',
                'data'    => [
                    'user_action'    => 'created',
                    'company_action' => 'created',
                    'email'          => 'dev@matias.com.co',
                    'company_dni'    => '900455420',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email'      => 'dev@matias.com.co',
            'first_name' => 'Juan',
            'last_name'  => 'Perez',
            'type_id'    => 3,
            'active'     => 1,
        ]);

        $this->assertDatabaseHas('companies', [
            'dni'          => '900455420',
            'company_name' => 'Matias ERP S.A.S.',
            'active'       => 1,
        ]);

        $user    = User::where('email', 'dev@matias.com.co')->first();
        $company = Company::where('dni', '900455420')->first();

        $this->assertNotNull($user->email_verified_at, 'El email debe quedar verificado.');
        $this->assertDatabaseHas('business_users', [
            'user_id'    => $user->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_el_hash_del_erp_se_guarda_sin_rehashear(): void
    {
        $hash = Hash::make('secret-del-erp');

        $this->sync($this->payload(['user' => ['password_hash' => $hash]]))->assertStatus(201);

        $user = User::where('email', 'dev@matias.com.co')->first();

        $this->assertSame($hash, $user->password, 'El hash debe guardarse tal cual, sin doble hashing.');
        $this->assertTrue(
            Hash::check('secret-del-erp', $user->password),
            'El usuario debe poder entrar con la misma contraseña del ERP.'
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Idempotencia / upsert
    // ─────────────────────────────────────────────────────────────────

    public function test_segunda_llamada_identica_es_idempotente(): void
    {
        $this->sync($this->payload())->assertStatus(201);
        $this->sync($this->payload())->assertStatus(200)
            ->assertJson([
                'message' => 'Cuenta actualizada exitosamente',
                'data'    => ['user_action' => 'updated', 'company_action' => 'updated'],
            ]);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('business_users', 1);
    }

    public function test_actualiza_nombre_de_usuario_existente(): void
    {
        $this->sync($this->payload())->assertStatus(201);

        $this->sync($this->payload([
            'user' => ['first_name' => 'Juana', 'last_name' => 'Gomez'],
        ]))->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'email'      => 'dev@matias.com.co',
            'first_name' => 'Juana',
            'last_name'  => 'Gomez',
        ]);
    }

    public function test_sobrescribe_el_password_de_una_cuenta_existente(): void
    {
        $original = Hash::make('password-original-del-usuario');

        User::create([
            'email'      => 'victima@maticerts.com',
            'password'   => $original,
            'first_name' => 'Victima',
            'last_name'  => 'Existente',
            'type_id'    => 2,
            'active'     => 1,
        ]);

        $atacante = Hash::make('password-del-atacante');

        $this->sync($this->payload([
            'user' => ['email' => 'victima@maticerts.com', 'password_hash' => $atacante],
        ]))->assertStatus(200);

        $user = User::where('email', 'victima@maticerts.com')->first();

        // Documenta el comportamiento actual: el sync reescribe la credencial.
        $this->assertSame($atacante, $user->password);
        $this->assertFalse(Hash::check('password-original-del-usuario', $user->password));
    }

    public function test_vincula_usuario_nuevo_a_empresa_existente(): void
    {
        $this->sync($this->payload())->assertStatus(201);

        $this->sync($this->payload([
            'user' => ['email' => 'otro@matias.com.co'],
        ]))->assertStatus(201);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('business_users', 2);
    }

    // ─────────────────────────────────────────────────────────────────
    // Validación
    // ─────────────────────────────────────────────────────────────────

    public function test_rechaza_type_id_administrador(): void
    {
        $this->sync($this->payload(['user' => ['type_id' => 1]]))
            ->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_rechaza_email_invalido(): void
    {
        $this->sync($this->payload(['user' => ['email' => 'no-es-un-email']]))
            ->assertStatus(422);
    }

    public function test_rechaza_hash_de_longitud_incorrecta(): void
    {
        $this->sync($this->payload(['user' => ['password_hash' => 'corto']]))
            ->assertStatus(422);
    }

    public function test_acepta_un_hash_no_bcrypt_de_60_caracteres(): void
    {
        $basura = str_repeat('x', 60);

        $response = $this->sync($this->payload(['user' => ['password_hash' => $basura]]));

        // Documenta el comportamiento actual: solo se valida la longitud.
        $response->assertStatus(201);
        $this->assertSame($basura, User::where('email', 'dev@matias.com.co')->first()->password);
    }

    public function test_rechaza_payload_sin_empresa(): void
    {
        $payload = $this->payload();
        unset($payload['company']);

        $this->sync($payload)->assertStatus(422);
    }

    public function test_rechaza_city_id_inexistente(): void
    {
        $this->sync($this->payload(['company' => ['city_id' => 999999]]))
            ->assertStatus(422);
    }

    public function test_aplica_defaults_de_pais_y_documento_cuando_no_se_envian(): void
    {
        $payload = $this->payload();
        unset($payload['company']['country_id'], $payload['company']['city_id']);

        $this->sync($payload)->assertStatus(201);

        $this->assertDatabaseHas('companies', [
            'dni'                  => '900455420',
            'country_id'           => 45,
            'identity_document_id' => 3,
            'type_organization_id' => 1,
        ]);
    }

    public function test_usa_el_email_del_usuario_cuando_la_empresa_no_trae_email(): void
    {
        $payload = $this->payload();
        unset($payload['company']['email']);

        $this->sync($payload)->assertStatus(201);

        $this->assertDatabaseHas('companies', [
            'dni'   => '900455420',
            'email' => 'dev@matias.com.co',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Atomicidad
    // ─────────────────────────────────────────────────────────────────

    public function test_no_deja_usuario_huerfano_si_falla_la_creacion_de_empresa(): void
    {
        Schema::drop('companies');

        $response = $this->sync($this->payload());

        $this->assertNotEquals(201, $response->getStatusCode());

        // Si la transacción no revirtiera, quedaría un usuario sin empresa.
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('business_users', 0);
    }
}
