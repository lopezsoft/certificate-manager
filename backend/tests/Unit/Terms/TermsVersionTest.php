<?php

declare(strict_types=1);

namespace Tests\Unit\Terms;

use App\Console\Commands\PublishTermsVersionCommand;
use App\Enums\TermsConsentScopeEnum;
use App\Http\Requests\Certificate\CreateCertificateRequestFormRequest;
use App\Models\TermsVersion;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas puras (sin BD) del versionado de Términos y Condiciones:
 * normalización + hash reproducible, extracción de texto del HTML oficial
 * y presencia de las reglas de consentimiento en el FormRequest.
 */
class TermsVersionTest extends TestCase
{
    /** @test */
    public function normalize_is_idempotent_and_ignores_trivial_whitespace_differences(): void
    {
        $a = "Hola   mundo\r\n\r\n\r\n\r\nSegundo  párrafo \t final\r\n";
        $b = "Hola mundo\n\nSegundo párrafo final";

        $na = TermsVersion::normalizeContent($a);
        $nb = TermsVersion::normalizeContent($b);

        $this->assertSame($nb, $na);
        $this->assertSame($na, TermsVersion::normalizeContent($na));
        $this->assertSame(TermsVersion::hashContent($na), TermsVersion::hashContent($nb));
    }

    /** @test */
    public function hash_changes_when_the_text_changes(): void
    {
        $v1 = TermsVersion::normalizeContent('El plazo es de siete (7) días calendario.');
        $v2 = TermsVersion::normalizeContent('El plazo es de diez (10) días calendario.');

        $this->assertNotSame(TermsVersion::hashContent($v1), TermsVersion::hashContent($v2));
        $this->assertSame(64, strlen(TermsVersion::hashContent($v1)));
    }

    /** @test */
    public function extract_text_from_html_keeps_body_text_and_drops_chrome(): void
    {
        $html = <<<HTML
        <html><head><title>T&amp;C</title><style>p{}</style></head>
        <body><header>MENU</header><nav>nav</nav>
        <main>
          <h2>5. Pagos</h2>
          <p>Texto&nbsp;con <strong>énfasis</strong> y &quot;comillas&quot;.</p>
          <ul><li>Punto uno</li><li>Punto dos</li></ul>
          <script>alert(1)</script>
        </main>
        <footer>PIE</footer></body></html>
        HTML;

        $text = TermsVersion::normalizeContent(PublishTermsVersionCommand::extractTextFromHtml($html));

        $this->assertStringContainsString('5. Pagos', $text);
        $this->assertStringContainsString('Texto con énfasis y "comillas".', $text);
        $this->assertStringContainsString("Punto uno\nPunto dos", $text);
        $this->assertStringNotContainsString('MENU', $text);
        $this->assertStringNotContainsString('PIE', $text);
        $this->assertStringNotContainsString('alert', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    /** @test */
    public function create_request_rules_require_terms_acceptance_and_version(): void
    {
        $rules = (new CreateCertificateRequestFormRequest())->rules();

        $this->assertSame(['required', 'accepted'], $rules['accept_terms']);
        $this->assertContains('required', $rules['terms_version_id']);
        $this->assertContains('exists:terms_versions,id', $rules['terms_version_id']);
    }

    /** @test */
    public function consent_scope_default_matches_ddl_default(): void
    {
        $ddl = file_get_contents(__DIR__ . '/../../../database/scripts/2026/09/DDL_create_terms_versions_and_acceptances.sql');

        $this->assertStringContainsString(
            "DEFAULT '" . TermsConsentScopeEnum::MATICERTS_TERMS_IMMEDIATE_EXECUTION->value . "'",
            $ddl
        );
    }
}
