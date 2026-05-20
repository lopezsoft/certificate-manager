<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Http;

/**
 * Firmador OAuth 1.0 HMAC-SHA1 (2-legged / consumer-only).
 *
 * Implementación minimalista del RFC 5849 acotada a lo que exige Viafirma RA:
 *  - signature_method = HMAC-SHA1
 *  - version          = 1.0
 *  - consumer_key + consumer_secret (sin token de usuario)
 *  - signing_key      = rawurlencode(consumer_secret) . '&' (token vacío)
 *
 * IMPORTANTE: La validación de credenciales es LAZY — solo se ejecuta en
 * buildAuthorizationHeader(), NUNCA en el constructor. Esto permite que el
 * container registre el singleton sin requerir que las env vars estén
 * configuradas (necesario para route:list, config:cache, y requests HTTP
 * que no usan Viafirma).
 *
 * Es testable de forma independiente (V-106) y sin dependencias externas, lo
 * que evita instalar `guzzlehttp/oauth-subscriber` y mantiene control total.
 */
final class OAuth1Signer
{
    public function __construct(
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
    ) {}

    /**
     * Construye el header `Authorization: OAuth ...` para una request.
     *
     * @param string                $method      Método HTTP (GET/POST/...)
     * @param string                $url         URL absoluta SIN query string.
     * @param array<string,string>  $queryParams Pares query (k=>v) ya decodificados.
     * @param array<string,string>  $bodyParams  Para form-urlencoded; vacío si el body es JSON o binario.
     * @param string|null           $nonce       Inyectable para tests; null = random.
     * @param int|null              $timestamp   Inyectable para tests; null = time().
     */
    public function buildAuthorizationHeader(
        string $method,
        string $url,
        array $queryParams = [],
        array $bodyParams = [],
        ?string $nonce = null,
        ?int $timestamp = null,
    ): string {
        $this->ensureCredentialsConfigured();

        $oauthParams = [
            'oauth_consumer_key'     => $this->consumerKey,
            'oauth_nonce'            => $nonce ?? bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => (string) ($timestamp ?? time()),
            'oauth_version'          => '1.0',
        ];

        $signatureBaseString = $this->buildSignatureBaseString(
            method: strtoupper($method),
            url: $url,
            params: array_merge($oauthParams, $queryParams, $bodyParams),
        );

        $signingKey = $this->rfc3986encode($this->consumerSecret) . '&'; // sin token
        $signature = base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));

        $oauthParams['oauth_signature'] = $signature;

        // Sólo los oauth_* van en el header, ordenados alfabéticamente.
        ksort($oauthParams);
        $parts = [];
        foreach ($oauthParams as $k => $v) {
            $parts[] = sprintf('%s="%s"', $this->rfc3986encode($k), $this->rfc3986encode($v));
        }
        return 'OAuth ' . implode(', ', $parts);
    }

    /** Expuesto para testing. */
    public function buildSignatureBaseString(string $method, string $url, array $params): string
    {
        // 1. Normalizar parámetros: rfc3986-encode keys/values, ordenar lex.
        $encoded = [];
        foreach ($params as $k => $v) {
            $encoded[] = [$this->rfc3986encode((string) $k), $this->rfc3986encode((string) $v)];
        }
        usort($encoded, function (array $a, array $b): int {
            return $a[0] === $b[0] ? strcmp($a[1], $b[1]) : strcmp($a[0], $b[0]);
        });

        $paramString = implode('&', array_map(fn ($p) => $p[0] . '=' . $p[1], $encoded));

        return strtoupper($method)
            . '&' . $this->rfc3986encode($this->normalizeUrl($url))
            . '&' . $this->rfc3986encode($paramString);
    }

    /** RFC 3986 percent-encoding (más estricto que rawurlencode para '~'). */
    public function rfc3986encode(string $value): string
    {
        // rawurlencode ya implementa RFC 3986 desde PHP 5.3, pero blindamos
        // explícitamente por si alguna implementación local difiriera.
        return str_replace(['+', '%7E'], ['%20', '~'], rawurlencode($value));
    }

    /** Normaliza la URL al "base string URI" del RFC 5849 §3.4.1.2. */
    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }
        $scheme = strtolower($parts['scheme']);
        $host   = strtolower($parts['host']);
        $port   = isset($parts['port']) ? (int) $parts['port'] : null;
        $defaultPort = $scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : null);
        $portPart = ($port !== null && $port !== $defaultPort) ? ':' . $port : '';
        $path = $parts['path'] ?? '/';
        return $scheme . '://' . $host . $portPart . $path;
    }

    /**
     * Valida que las credenciales OAuth estén configuradas.
     *
     * Se invoca de forma lazy (al firmar), no en construcción,
     * para no bloquear comandos de introspección del framework
     * (route:list, config:cache, etc.).
     *
     * @throws \RuntimeException Si falta consumer key o consumer secret.
     */
    private function ensureCredentialsConfigured(): void
    {
        if ($this->consumerKey === '' || $this->consumerSecret === '') {
            throw new \RuntimeException(
                'VIAFIRMA_CLIENT_ID / VIAFIRMA_CLIENT_SECRET no están configurados. '
                . 'Revisa las variables de entorno en tu archivo .env.'
            );
        }
    }
}
