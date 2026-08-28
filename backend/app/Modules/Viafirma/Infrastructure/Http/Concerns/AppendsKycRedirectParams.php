<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Http\Concerns;

/**
 * Agrega parámetros de redirección post-verificación al link de acreditación
 * KYC (MetaMap). Según confirmó Viafirma: al completar la verificación y
 * mostrar la pantalla de confirmación, MetaMap redirige automáticamente al
 * usuario a la URL indicada en `redirect` (con `target=_self` para navegar
 * en la misma pestaña) — sin necesidad de desarrollo adicional de nuestro lado.
 *
 * El `redirect` apunta a nuestro propio callback público
 * (`ViafirmaKycCallbackController`, ruta `viafirma.kyc-callback`), que
 * registra la finalización del flujo y reenvía al destino final configurado
 * en `viafirma.kyc.completed_path`. Desactivable vía `VIAFIRMA_KYC_CALLBACK_ENABLED=false`.
 */
trait AppendsKycRedirectParams
{
    private function appendKycRedirectParams(string $link, string $publicId): string
    {
        if ($publicId === '' || !config('viafirma.kyc.callback_enabled', true)) {
            return $link;
        }

        $parts = parse_url($link);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $link;
        }

        parse_str($parts['query'] ?? '', $query);
        $query['redirect'] = route('viafirma.kyc-callback', ['publicId' => $publicId]);
        $query['target']   = (string) config('viafirma.kyc.redirect_target', '_self');
        $parts['query']    = http_build_query($query);

        return $this->rebuildUrl($parts);
    }

    /**
     * @param array{scheme?: string, host?: string, port?: int, path?: string, query?: string, fragment?: string} $parts
     */
    private function rebuildUrl(array $parts): string
    {
        $url = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        $url .= $parts['path'] ?? '';
        if (!empty($parts['query'])) {
            $url .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }
}
