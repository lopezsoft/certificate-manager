<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Http;

use App\Modules\Viafirma\Application\DTOs\SubmitCsrInputDto;
use App\Modules\Viafirma\Application\DTOs\SubmitCsrResultDto;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException;
use App\Modules\Viafirma\Infrastructure\Http\Concerns\AppendsKycRedirectParams;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * Cliente Viafirma RA basado en Guzzle (sin Saloon — cero dependencias nuevas).
 *
 * Patrón: Adapter del port {@see ViafirmaClient}. Encapsula los detalles HTTP
 * y traduce errores 5xx/429/ConnectException → {@see TransientHttpException}
 * (señal para el circuit breaker + retry con backoff del Sprint 3).
 */
final class GuzzleViafirmaClient implements ViafirmaClient
{
    use AppendsKycRedirectParams;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly OAuth1Signer $signer,
        private readonly ProfileResponseParser $parser,
        private readonly string $baseUrl,
        private readonly SafePemLogger $logger,
        private readonly int $timeout = 30,
    ) {
        if ($this->baseUrl === '') {
            throw new ViafirmaClientException(
                'config(viafirma.base_url) está vacía: la URL del API es obligatoria por entorno.'
            );
        }
    }

    public function getProfiles(string $raCode): array
    {
        if ($raCode === '') {
            throw new ViafirmaClientException('raCode no puede ser vacío.');
        }

        $url   = $this->urlFor('/ra/available-profiles');
        $query = ['codRa' => $raCode];

        $this->logger->info('viafirma.getProfiles.request', ['raCode' => $raCode, 'url' => $url]);

        $decoded = $this->send('GET', $url, queryParams: $query);

        $profiles = $this->parser->parse($decoded);
        $this->logger->info('viafirma.getProfiles.response', [
            'raCode' => $raCode,
            'count'  => count($profiles),
        ]);

        // Log del body crudo cuando no se obtienen perfiles para facilitar diagnóstico
        if (count($profiles) === 0) {
            $this->logger->warning('viafirma.getProfiles.empty', [
                'raCode'       => $raCode,
                'raw_response' => $decoded,
                'hint'         => 'Verifique VIAFIRMA_RA_CODE y que el RA tenga perfiles en el entorno configurado.',
            ]);
        }

        return $profiles;
    }

    public function submitCsr(SubmitCsrInputDto $input): SubmitCsrResultDto
    {
        $url     = $this->urlFor('/request/fromCSR');
        $payload = $input->toViafirmaPayload();

        // Loggear sin csr ni emails (el SafePemLogger ya redacta PEMs, pero el
        // CSR base64 sin cabeceras no matchea el regex; lo omitimos explícitamente).
        $this->logger->info('viafirma.submitCsr.request', [
            'url'              => $url,
            'identityType'     => $payload['identityType'],
            'identity'         => $payload['identity'],  // ← IMPORTANTE: valor enviado a Viafirma
            'countryCode'      => $payload['countryCode'],
            'ra'               => $payload['ra'],
            'organizationType' => $payload['organizationType'] ?? null,
            'codProfile'       => substr($payload['codProfile'], 0, 12) . '…',
            'csr_len'          => strlen($payload['csr']),
            'payload_keys'     => implode(', ', array_keys($payload)),
        ]);

        $decoded = $this->send('POST', $url, jsonBody: $payload);

        $codRequest = (string) ($decoded['codRequest'] ?? $decoded['cod_request'] ?? '');
        if ($codRequest === '') {
            throw new ViafirmaClientException(
                'Respuesta de submitCsr sin `codRequest`: ' . json_encode($decoded)
            );
        }

        // API v3.4.53: publicId es OBLIGATORIO en la respuesta del POST /request/fromCSR.
        $publicId = (string) ($decoded['publicId'] ?? $decoded['public_id'] ?? '');
        if ($publicId === '') {
            throw new ViafirmaClientException(
                "Respuesta de submitCsr sin `publicId` para codRequest={$codRequest}: " . json_encode($decoded)
            );
        }

        $initialStatus = isset($decoded['status']) ? (string) $decoded['status'] : null;

        $this->logger->info('viafirma.submitCsr.response', [
            'codRequest'    => $codRequest,
            'publicId'      => $publicId,
            'initialStatus' => $initialStatus,
        ]);

        return new SubmitCsrResultDto(
            codRequest:    $codRequest,
            publicId:      $publicId,
            initialStatus: $initialStatus,
            raw:           $decoded,
        );
    }

    // NOTA: getPublicId() ELIMINADO — API v3.4.53 devuelve publicId directamente
    // en la respuesta de POST /request/fromCSR. Ya no existe el endpoint
    // GET /request/{codRequest}/publicId.

    public function getStatus(string $codRequest): \App\Modules\Viafirma\Application\DTOs\StatusResultDto
    {
        if ($codRequest === '') {
            throw new ViafirmaClientException('codRequest no puede ser vacío.');
        }

        $url = $this->urlFor('/request/' . rawurlencode($codRequest) . '/status');
        $this->logger->info('viafirma.getStatus.request', ['codRequest' => $codRequest, 'url' => $url]);

        $decoded = $this->send('GET', $url);

        // API v3.4.53: el campo que trae el estado es "code", no "status" ni "state".
        // Ejemplo de respuesta: {"code": "rues_check"}
        $statusValue = (string) ($decoded['code'] ?? $decoded['status'] ?? $decoded['state'] ?? '');
        if ($statusValue === '') {
            throw new ViafirmaClientException(
                "Respuesta de getStatus sin campo `code` para codRequest={$codRequest}: " . json_encode($decoded)
            );
        }

        $remoteStatus = \App\Modules\Viafirma\Domain\Enums\RemoteStatus::tryFrom($statusValue);
        if ($remoteStatus === null) {
            $this->logger->warning('viafirma.getStatus.unknown_status', [
                'codRequest' => $codRequest,
                'status'     => $statusValue,
            ]);
            throw new ViafirmaClientException(
                "Estado remoto desconocido '{$statusValue}' para codRequest={$codRequest}."
            );
        }

        $this->logger->info('viafirma.getStatus.response', [
            'codRequest' => $codRequest,
            'status'     => $remoteStatus->value,
        ]);

        return new \App\Modules\Viafirma\Application\DTOs\StatusResultDto(
            status:     $remoteStatus,
            codRequest: $codRequest,
            raw:        $decoded,
        );
    }

    /**
     * API v3.4.53: descarga el P7B vía `downloadCertificateServlet?req={publicId}`.
     *
     * Reemplaza la URL legacy `/request/{codRequest}/download/pkcs7`.
     */
    public function downloadP7b(string $publicId): string
    {
        if ($publicId === '') {
            throw new ViafirmaClientException('publicId no puede ser vacío para descarga P7B.');
        }

        $downloadBase = rtrim((string) config('viafirma.download_url'), '/');
        if ($downloadBase === '') {
            throw new ViafirmaClientException(
                'config(viafirma.download_url) está vacía: la URL de descarga es obligatoria.'
            );
        }

        // API v3.4.53: {download_url}/downloadCertificateServlet?req={publicId}
        $url         = $downloadBase . '/downloadCertificateServlet';
        $queryParams = ['req' => $publicId];

        $this->logger->info('viafirma.downloadP7b.request', ['publicId' => $publicId, 'url' => $url]);

        $authHeader = $this->signer->buildAuthorizationHeader(
            method:      'GET',
            url:         $url,
            queryParams: $queryParams,
        );

        $options = [
            'query'   => $queryParams,
            'headers' => [
                'Authorization' => $authHeader,
                'Accept'        => 'application/x-pkcs7-certificates, application/octet-stream',
            ],
            'timeout'         => $this->timeout,
            'connect_timeout' => min(10, $this->timeout),
            'http_errors'     => true,
        ];

        try {
            $response = $this->http->request('GET', $url, $options);
        } catch (ConnectException $e) {
            throw new TransientHttpException('Fallo de red descargando P7B: ' . $e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode() ?? 0;
            if ($status >= 500 || $status === 429) {
                throw new TransientHttpException(
                    "Viafirma respondió {$status} en descarga P7B: " . substr((string) ($e->getResponse()?->getBody() ?? ''), 0, 200),
                    $status,
                    $e,
                );
            }
            throw new ViafirmaClientException(
                "Viafirma respondió {$status} en descarga P7B para publicId={$publicId}",
                $status,
                $e,
            );
        }

        $binary = (string) $response->getBody();

        if ($binary === '') {
            throw new ViafirmaClientException("Descarga P7B vacía para publicId={$publicId}.");
        }

        $contentType = $response->getHeaderLine('Content-Type');
        $this->logger->info('viafirma.downloadP7b.response', [
            'publicId'    => $publicId,
            'size_bytes'  => strlen($binary),
            'contentType' => $contentType,
        ]);

        return $binary;
    }

    // ── Nuevos endpoints ───────────────────────────────────────────────────

    public function revokeCertificate(string $revokingCode, int $revocationReason): string
    {
        if ($revokingCode === '') {
            throw new ViafirmaClientException('revokingCode no puede ser vacío.');
        }

        $url     = $this->urlFor('/request/revoke/code/' . rawurlencode($revokingCode));
        $payload = ['revocationReason' => $revocationReason];

        $this->logger->info('viafirma.revoke.request', [
            'url'              => $url,
            'revocationReason' => $revocationReason,
        ]);

        $decoded = $this->send('POST', $url, jsonBody: $payload);

        $newCode = (string) ($decoded['code'] ?? '');
        if ($newCode === '') {
            throw new ViafirmaClientException(
                'Respuesta de revokeCertificate sin campo `code`: ' . json_encode($decoded)
            );
        }

        $this->logger->info('viafirma.revoke.response', [
            'revokingCode' => $revokingCode,
            'newCode'      => $newCode,
        ]);

        return $newCode;
    }

    public function getAccreditationLink(string $codRequest, string $publicId): string
    {
        if ($codRequest === '') {
            throw new ViafirmaClientException('codRequest no puede ser vacío.');
        }

        $url = $this->urlFor('/services/accreditation/' . rawurlencode($codRequest));

        $this->logger->info('viafirma.kyc_link.request', [
            'codRequest' => $codRequest,
            'url'        => $url,
        ]);

        $decoded = $this->send('GET', $url);

        $link = (string) ($decoded['link'] ?? '');
        if ($link === '') {
            throw new ViafirmaClientException(
                "Respuesta de getAccreditationLink sin campo `link` para codRequest={$codRequest}: " . json_encode($decoded)
            );
        }

        $link = $this->appendKycRedirectParams($link, $publicId);

        $this->logger->info('viafirma.kyc_link.response', [
            'codRequest' => $codRequest,
            'link'       => $link,
        ]);

        return $link;
    }

    public function getRevocationCode(string $codRequest): string
    {
        if ($codRequest === '') {
            throw new ViafirmaClientException('codRequest no puede ser vacío.');
        }

        $url = $this->urlFor('/request/' . rawurlencode($codRequest) . '/revocationCode');

        $this->logger->info('viafirma.revocation_code.request', [
            'codRequest' => $codRequest,
            'url'        => $url,
        ]);

        $decoded = $this->send('GET', $url);

        $revocationCode = (string) ($decoded['revocationCode'] ?? '');
        if ($revocationCode === '') {
            throw new ViafirmaClientException(
                "Respuesta de getRevocationCode sin campo `revocationCode` para codRequest={$codRequest}: " . json_encode($decoded)
            );
        }

        $this->logger->info('viafirma.revocation_code.response', [
            'codRequest'     => $codRequest,
            'revocationCode' => $revocationCode,
        ]);

        return $revocationCode;
    }

    public function uploadFiles(string $codRequest, array $files): array
    {
        if ($codRequest === '') {
            throw new ViafirmaClientException('codRequest no puede ser vacío.');
        }
        if (empty($files)) {
            throw new ViafirmaClientException('Debe proveer al menos un archivo para adjuntar.');
        }

        $url = $this->urlFor('/files/upload/');

        // API v3.4.53: la clave del payload es "codeRequest" (no "codRequest").
        $payload = [
            'codeRequest' => $codRequest,
            'files'       => $files,
        ];

        $this->logger->info('viafirma.files.upload.request', [
            'codRequest' => $codRequest,
            'fileNames'  => array_column($files, 'name'),
            'fileCount'  => count($files),
        ]);

        $decoded = $this->send('POST', $url, jsonBody: $payload);

        $this->logger->info('viafirma.files.upload.response', [
            'codRequest' => $codRequest,
            'uploaded'   => array_column($decoded, 'name'),
        ]);

        return $decoded;
    }

    public function listFiles(string $codRequest): array
    {
        if ($codRequest === '') {
            throw new ViafirmaClientException('codRequest no puede ser vacío.');
        }

        $url = $this->urlFor('/files/list/' . rawurlencode($codRequest));

        $this->logger->info('viafirma.files.list.request', ['codRequest' => $codRequest, 'url' => $url]);

        $decoded = $this->send('GET', $url);

        $this->logger->info('viafirma.files.list.response', [
            'codRequest' => $codRequest,
            'count'      => count($decoded),
        ]);

        return $decoded;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * @param array<string,string>  $queryParams
     * @param array<string,mixed>|null $jsonBody
     * @return array<string,mixed>
     */
    private function send(
        string $method,
        string $url,
        array $queryParams = [],
        ?array $jsonBody = null,
    ): array {
        // OAuth 1.0 firma: el body JSON NO entra en la signature base string
        // (sólo aplica para application/x-www-form-urlencoded — RFC 5849 §3.4.1.3.1).
        $authHeader = $this->signer->buildAuthorizationHeader(
            method:      $method,
            url:         $url,
            queryParams: $queryParams,
        );

        $options = [
            'query'           => $queryParams,
            'headers'         => array_filter([
                'Authorization' => $authHeader,
                'Accept'        => 'application/json',
                'Content-Type'  => $jsonBody !== null ? 'application/json' : null,
            ]),
            'timeout'         => $this->timeout,
            'connect_timeout' => min(10, $this->timeout),
            'http_errors'     => true,
        ];
        if ($jsonBody !== null) {
            $options['json'] = $jsonBody;
        }

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (ConnectException $e) {
            throw new TransientHttpException('Fallo de red contactando Viafirma: ' . $e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode() ?? 0;
            $bodySnippet = $e->getResponse() !== null
                ? substr((string) $e->getResponse()->getBody(), 0, 500)
                : '';

            if ($status >= 500 || $status === 429) {
                throw new TransientHttpException(
                    "Viafirma respondió {$status} en {$method} {$url}: {$bodySnippet}",
                    $status,
                    $e,
                );
            }

            // 404 con errorCode=request_not_found → el codRequest ya no existe del
            // lado de Viafirma (común en su sandbox, que purga solicitudes de prueba
            // periódicamente). Es terminal: reintentar nunca cambiará el resultado.
            if ($status === 404) {
                $decodedBody = json_decode($bodySnippet, true);
                if (is_array($decodedBody) && ($decodedBody['errorCode'] ?? null) === 'request_not_found') {
                    throw new \App\Modules\Viafirma\Domain\Exceptions\ViafirmaRequestNotFoundException(
                        "Viafirma no encuentra la solicitud (codRequest ya no existe) en {$method} {$url}: {$bodySnippet}",
                        $status,
                        $e,
                    );
                }
            }

            throw new ViafirmaClientException(
                "Viafirma respondió {$status} en {$method} {$url}: {$bodySnippet}",
                $status,
                $e,
            );
        }

        $raw = (string) $response->getBody();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ViafirmaClientException(
                "Respuesta de Viafirma no es JSON válido en {$method} {$url}."
            );
        }
        return $decoded;
    }

    private function urlFor(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }
}

