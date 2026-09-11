<?php

namespace App\Console\Commands;

use App\Models\TermsVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * PublishTermsVersionCommand
 *
 * Publica una nueva versión de los Términos y Condiciones tomando el texto
 * de la fuente oficial (https://maticerts.com/terminos/), calculando su
 * SHA-256 y guardando un snapshot íntegro en `terms_versions`. La versión
 * publicada queda marcada como vigente y las anteriores dejan de serlo.
 *
 * El snapshot en BD es la evidencia legal: el sitio puede cambiar, pero el
 * texto exacto que aceptó cada usuario queda asociado por hash.
 *
 * Uso:
 *   php artisan terms:publish --tag=1.0
 *   php artisan terms:publish --tag=1.1 --url=https://maticerts.com/terminos/
 *   php artisan terms:publish --tag=1.1 --file=/ruta/terminos.txt   (fuente local alternativa)
 *   php artisan terms:publish --tag=1.1 --dry-run                   (muestra hash sin guardar)
 */
class PublishTermsVersionCommand extends Command
{
    protected $signature = 'terms:publish
                            {--tag= : Etiqueta de versión (ej. 1.0). Obligatoria y única. (--version está reservada por Artisan)}
                            {--url= : URL pública del documento (default: config terms.source_url)}
                            {--file= : Ruta local a un archivo de texto/markdown en vez de descargar}
                            {--dry-run : Calcula el hash y muestra el resumen sin persistir}';

    protected $description = 'Publica una nueva versión vigente de los Términos y Condiciones (snapshot + hash SHA-256)';

    public function handle(): int
    {
        $version = trim((string) $this->option('tag'));
        if ($version === '') {
            $this->error('La opción --tag es obligatoria (ej. --tag=1.0).');
            return self::INVALID;
        }

        if (!Schema::hasTable('terms_versions')) {
            if (!$this->option('dry-run')) {
                $this->error('La tabla terms_versions no existe. Ejecute primero database/scripts/2026/09/DDL_create_terms_versions_and_acceptances.sql');
                return self::FAILURE;
            }
            $this->warn('Tabla terms_versions no existe: dry-run sin comparar contra versiones previas.');
        }

        $tableReady = Schema::hasTable('terms_versions');

        if ($tableReady && TermsVersion::where('version', $version)->exists()) {
            $this->error("La versión '{$version}' ya fue publicada. Las versiones son inmutables: use una etiqueta nueva.");
            return self::INVALID;
        }

        $sourceUrl = (string) ($this->option('url') ?: config('terms.source_url'));

        try {
            $rawContent = $this->option('file')
                ? $this->readLocalFile((string) $this->option('file'))
                : $this->fetchRemote($sourceUrl);
        } catch (Throwable $e) {
            $this->error('No fue posible obtener el documento: ' . $e->getMessage());
            return self::FAILURE;
        }

        $content = TermsVersion::normalizeContent($rawContent);
        if (mb_strlen($content) < 500) {
            $this->error('El contenido obtenido es demasiado corto (' . mb_strlen($content) . ' caracteres). Verifique la fuente.');
            return self::FAILURE;
        }

        $hash = TermsVersion::hashContent($content);

        $previous = $tableReady ? TermsVersion::current() : null;
        if ($previous !== null && $previous->content_hash === $hash) {
            $this->warn("El texto es idéntico a la versión vigente ({$previous->version}). No se publica nada.");
            return self::SUCCESS;
        }

        $this->table(['Campo', 'Valor'], [
            ['Versión', $version],
            ['Fuente', $this->option('file') ?: $sourceUrl],
            ['Caracteres', (string) mb_strlen($content)],
            ['SHA-256', $hash],
            ['Reemplaza a', $previous?->version ?? '(ninguna)'],
        ]);

        if ($this->option('dry-run')) {
            $this->info('Dry-run: no se guardó nada.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($version, $sourceUrl, $hash, $content): void {
            TermsVersion::query()->current()->update(['is_current' => false]);

            TermsVersion::create([
                'version'      => $version,
                'published_at' => now('UTC'),
                'source_url'   => $sourceUrl,
                'content_hash' => $hash,
                'content'      => $content,
                'is_current'   => true,
            ]);
        });

        Log::info("[TERMS] Publicada versión {$version} de T&C (sha256={$hash})");
        $this->info("Versión {$version} publicada y marcada como vigente.");

        return self::SUCCESS;
    }

    private function readLocalFile(string $path): string
    {
        if (!is_readable($path)) {
            throw new \RuntimeException("Archivo no legible: {$path}");
        }

        return (string) file_get_contents($path);
    }

    /**
     * Descarga la página oficial y extrae el texto legible del cuerpo
     * (sin scripts, estilos, navegación ni pie), conservando saltos de
     * párrafo para que el snapshot sea legible por un humano.
     */
    private function fetchRemote(string $url): string
    {
        $response = Http::timeout(20)->retry(2, 500)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} al consultar {$url}");
        }

        return self::extractTextFromHtml($response->body());
    }

    public static function extractTextFromHtml(string $html): string
    {
        if (preg_match('/<main\b.*?<\/main>|<article\b.*?<\/article>/is', $html, $m)) {
            $html = $m[0];
        }

        $html = preg_replace('/<(script|style|nav|header|footer)\b.*?<\/\1>/is', '', $html) ?? $html;
        $html = preg_replace('/<\/(p|div|li|h[1-6]|tr|section)>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return $text;
    }
}
