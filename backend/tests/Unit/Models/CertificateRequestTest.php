<?php

namespace Tests\Unit\Models;

use App\Models\CertificateRequest;
use Tests\TestCase;

/**
 * Tests unitarios para el modelo CertificateRequest.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class CertificateRequestTest extends TestCase
{
    public function test_expiration_date_formatted_retorna_null_cuando_fecha_es_null(): void
    {
        $cert = CertificateRequest::make(['expiration_date' => null]);

        $this->assertNull($cert->expiration_date_formatted);
    }

    public function test_expiration_date_formatted_retorna_string_cuando_fecha_tiene_valor(): void
    {
        $cert = CertificateRequest::make(['expiration_date' => '2026-12-31 23:59:59']);

        $formatted = $cert->expiration_date_formatted;

        $this->assertNotNull($formatted);
        $this->assertIsString($formatted);
        $this->assertMatchesRegularExpression('/\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2} (am|pm)/', $formatted);
    }

    public function test_expiration_date_formatted_esta_en_appends(): void
    {
        $cert = CertificateRequest::make([
            'expiration_date' => '2026-06-15 10:00:00',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $array = $cert->toArray();

        $this->assertArrayHasKey('expiration_date_formatted', $array);
    }

    public function test_fillable_contiene_campos_esperados(): void
    {
        $cert = new CertificateRequest();
        $fillable = $cert->getFillable();

        $this->assertContains('request_status', $fillable);
        $this->assertContains('company_id', $fillable);
        $this->assertContains('expiration_date', $fillable);
        $this->assertContains('dni', $fillable);
    }

    public function test_modelo_no_tiene_with_global(): void
    {
        $cert = new CertificateRequest();

        // $with debe estar vacío tras la refactorización T-18
        $reflection = new \ReflectionProperty($cert, 'with');
        $reflection->setAccessible(true);
        $withValue = $reflection->getValue($cert);

        $this->assertEmpty($withValue, 'El modelo no debe tener $with global para evitar N+1');
    }

    public function test_applicant_display_name_para_persona_natural_solo_muestra_el_nombre(): void
    {
        $cert = CertificateRequest::make([
            'legal_rep_first_name' => 'Juan',
            'legal_rep_last_name'  => 'Perez',
            'company_name'         => null,
        ]);

        $this->assertSame('Juan Perez', $cert->applicantDisplayName());
    }

    public function test_applicant_display_name_para_persona_juridica_incluye_la_empresa_entre_parentesis(): void
    {
        $cert = CertificateRequest::make([
            'legal_rep_first_name' => 'Juan',
            'legal_rep_last_name'  => 'Perez',
            'company_name'         => 'ACME SAS',
        ]);

        $this->assertSame('Juan Perez (ACME SAS)', $cert->applicantDisplayName());
    }

    public function test_applicant_display_name_sin_nombre_de_representante_usa_la_empresa(): void
    {
        $cert = CertificateRequest::make([
            'legal_rep_first_name' => null,
            'legal_rep_last_name'  => null,
            'company_name'         => 'SOLO EMPRESA SAS',
        ]);

        $this->assertSame('SOLO EMPRESA SAS', $cert->applicantDisplayName());
    }

    public function test_applicant_display_name_sin_ningun_dato_retorna_fallback(): void
    {
        $cert = new CertificateRequest();

        $this->assertSame('Solicitante', $cert->applicantDisplayName());
    }
}

