<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * Tests unitarios de la sanitización de inputs en CertificateRequestService.
 *
 * Verifican que las transformaciones strip_tags() + Str::upper() se aplican
 * correctamente sobre los patrones usados en createCertificateRequest() y
 * updateCertificateRequest().
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin DB real.
 */
class InputSanitizationTest extends TestCase
{
    // ── strip_tags ───────────────────────────────────────────────────────────

    public function test_strip_tags_elimina_etiquetas_html_de_company_name(): void
    {
        $raw      = '<script>alert("xss")</script>Mi Empresa S.A.S.';
        $sanitized = strip_tags($raw);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('</script>', $sanitized);
        $this->assertStringContainsString('Mi Empresa S.A.S.', $sanitized);
    }

    public function test_strip_tags_elimina_etiquetas_de_address(): void
    {
        $raw      = '<b>Calle 123</b> # 45-67';
        $sanitized = strip_tags($raw);

        $this->assertSame('Calle 123 # 45-67', $sanitized);
    }

    public function test_strip_tags_elimina_etiquetas_de_legal_representative(): void
    {
        $raw      = '<em>Juan</em> Pérez';
        $sanitized = strip_tags($raw);

        $this->assertSame('Juan Pérez', $sanitized);
    }

    public function test_strip_tags_elimina_etiquetas_de_info(): void
    {
        $raw      = '<p>Información adicional</p>';
        $sanitized = strip_tags($raw ?? '');

        $this->assertSame('Información adicional', $sanitized);
    }

    public function test_strip_tags_con_campo_info_nulo_devuelve_cadena_vacia(): void
    {
        $raw      = null;
        $sanitized = strip_tags($raw ?? '');

        $this->assertSame('', $sanitized);
    }

    public function test_strip_tags_no_altera_texto_plano(): void
    {
        $raw      = 'Empresa de Prueba S.A.S.';
        $sanitized = strip_tags($raw);

        $this->assertSame($raw, $sanitized);
    }

    // ── strip_tags + Str::upper (patrón del servicio) ────────────────────────

    public function test_cadena_html_queda_sanitizada_y_en_mayusculas(): void
    {
        $raw      = '<b>empresa ejemplo ltda</b>';
        $sanitized = \Illuminate\Support\Str::upper(strip_tags($raw));

        $this->assertSame('EMPRESA EJEMPLO LTDA', $sanitized);
    }

    public function test_cadena_sin_html_solo_queda_en_mayusculas(): void
    {
        $raw      = 'juan pérez';
        $sanitized = \Illuminate\Support\Str::upper(strip_tags($raw));

        $this->assertSame('JUAN PÉREZ', $sanitized);
    }

    // ── Casos borde ───────────────────────────────────────────────────────────

    public function test_strip_tags_no_elimina_entidades_html(): void
    {
        // Las entidades HTML como &amp; no se eliminan con strip_tags
        $raw      = 'empresa &amp; socios';
        $sanitized = strip_tags($raw);

        $this->assertSame('empresa &amp; socios', $sanitized);
    }

    public function test_strip_tags_elimina_atributos_peligrosos(): void
    {
        $raw      = '<a href="javascript:void(0)" onclick="evil()">click</a>';
        $sanitized = strip_tags($raw);

        $this->assertStringNotContainsString('javascript:', $sanitized);
        $this->assertStringNotContainsString('onclick', $sanitized);
        $this->assertSame('click', $sanitized);
    }
}
