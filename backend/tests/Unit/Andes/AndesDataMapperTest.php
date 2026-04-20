<?php

namespace Tests\Unit\Andes;

use App\Andes\Services\AndesDataMapper;
use App\Andes\DTOs\CertificateEmissionRequest;
use App\Andes\DTOs\IdentityValidationRequest;
use App\Andes\Enums\AndesVigenciaEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests unitarios para AndesDataMapper.
 * Solo mocks — sin RefreshDatabase, sin escrituras reales.
 */
class AndesDataMapperTest extends TestCase
{
    private AndesDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new AndesDataMapper();
    }

    // ── splitFullName ────────────────────────────────────────────────────────

    public function test_split_full_name_cuatro_palabras(): void
    {
        $result = $this->mapper->splitFullName('LEWIS OSWALDO LOPEZ GOMEZ');

        $this->assertSame('LEWIS OSWALDO', $result['nombres']);
        $this->assertSame('LOPEZ GOMEZ', $result['apellidos']);
    }

    public function test_split_full_name_tres_palabras(): void
    {
        $result = $this->mapper->splitFullName('MARIA LOPEZ GOMEZ');

        $this->assertSame('MARIA LOPEZ', $result['nombres']);
        $this->assertSame('GOMEZ', $result['apellidos']);
    }

    public function test_split_full_name_dos_palabras(): void
    {
        $result = $this->mapper->splitFullName('JUAN PEREZ');

        $this->assertSame('JUAN PEREZ', $result['nombres']);
        $this->assertSame('', $result['apellidos']);
    }

    public function test_split_full_name_una_palabra(): void
    {
        $result = $this->mapper->splitFullName('CARLOS');

        $this->assertSame('CARLOS', $result['nombres']);
        $this->assertSame('', $result['apellidos']);
    }

    public function test_split_full_name_espacios_extra(): void
    {
        $result = $this->mapper->splitFullName('  PEDRO  JOSE  RAMIREZ  VILLA  ');

        $this->assertSame('PEDRO JOSE', $result['nombres']);
        $this->assertSame('RAMIREZ VILLA', $result['apellidos']);
    }

    // ── mapIdentityDocumentToAndes ───────────────────────────────────────────

    public function test_map_identity_document_retorna_andes_code(): void
    {
        Cache::flush();

        DB::shouldReceive('table')
            ->once()
            ->with('identity_documents')
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->once()
            ->with('id', 1)
            ->andReturnSelf();

        DB::shouldReceive('select')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('first')
            ->once()
            ->andReturn((object)[
                'id'            => 1,
                'code'          => '13',
                'document_name' => 'Cédula de Ciudadanía',
                'andes_code'    => 1,
            ]);

        $result = $this->mapper->mapIdentityDocumentToAndes(1);

        $this->assertSame(1, $result);
    }

    public function test_map_identity_document_lanza_excepcion_si_andes_code_es_null(): void
    {
        Cache::flush();

        DB::shouldReceive('table')->with('identity_documents')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object)[
            'id'            => 99,
            'code'          => '99',
            'document_name' => 'Documento sin configurar',
            'andes_code'    => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('andes_code configurado');

        $this->mapper->mapIdentityDocumentToAndes(99);
    }

    public function test_map_identity_document_lanza_excepcion_si_no_existe(): void
    {
        Cache::flush();

        DB::shouldReceive('table')->with('identity_documents')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no existe en identity_documents');

        $this->mapper->mapIdentityDocumentToAndes(999);
    }

    // ── mapOrganizationTypeToAndesCertType ───────────────────────────────────

    public function test_map_org_juridica_retorna_cert_type_10(): void
    {
        Cache::flush();

        DB::shouldReceive('table')->with('type_organization')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object)[
            'id'              => 1,
            'description'     => 'Persona Jurídica',
            'andes_cert_type' => 10,
        ]);

        $result = $this->mapper->mapOrganizationTypeToAndesCertType(1);

        $this->assertSame(10, $result);
    }

    public function test_map_org_natural_retorna_cert_type_11(): void
    {
        Cache::flush();

        DB::shouldReceive('table')->with('type_organization')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object)[
            'id'              => 2,
            'description'     => 'Persona Natural',
            'andes_cert_type' => 11,
        ]);

        $result = $this->mapper->mapOrganizationTypeToAndesCertType(2);

        $this->assertSame(11, $result);
    }

    // ── AndesVigenciaEnum ────────────────────────────────────────────────────

    public function test_vigencia_from_years_1_retorna_valor_3(): void
    {
        $vigencia = AndesVigenciaEnum::fromYears(1);
        $this->assertSame(AndesVigenciaEnum::UN_ANIO, $vigencia);
        $this->assertSame(3, $vigencia->value);
    }

    public function test_vigencia_from_years_2_retorna_valor_4(): void
    {
        $vigencia = AndesVigenciaEnum::fromYears(2);
        $this->assertSame(AndesVigenciaEnum::DOS_ANIOS, $vigencia);
        $this->assertSame(4, $vigencia->value);
    }

    public function test_vigencia_from_years_invalido_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AndesVigenciaEnum::fromYears(3);
    }
}

