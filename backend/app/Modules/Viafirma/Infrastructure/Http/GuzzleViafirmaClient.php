<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Http;

use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Cliente Viafirma RA basado en Guzzle (ya disponible en composer.json).
 *
 * Patrón: Adapter — implementa el port {@see ViafirmaClient} y aísla el
 * detalle HTTP del resto del módulo. Se puede sustituir por un Connector
 * Saloon (V-106 alternativa) sin afectar el dominio.
 *
 * NOTA: en Sprint 1 sólo se implementa `getProfiles()` (V-107). Los demás
 * endpoints se construyen en Sprints 2-4.
 */
final class GuzzleViafirmaClient implements ViafirmaClient
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly OAuth1Signer $signer,
        private readonly ProfileResponseParser $parser,
        private readonly string $baseUrl,
        private readonly LoggerInterface $logger,
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

        $url   = rtrim($this->baseUrl, '/') . '/ra/available-profiles';
        $query = ['codRa' => $raCode];

        $authHeader = $this->signer->buildAuthorizationHeader(
            method: 'GET',
            url: $url,
            queryParams: $query,
        );

        $this->logger->info('viafirma.getProfiles.request', ['raCode' => $raCode, 'url' => $url]);

        try {
            $response = $this->http->request('GET', $url, [
                'query'   => $query,
                'headers' => [
                    'Authorization' => $authHeader,
                    'Accept'        => 'application/json',
                ],
                'timeout'         => $this->timeout,
                'connect_timeout' => min(10, $this->timeout),
                'http_errors'     => true,
            ]);
        } catch (ConnectException $e) {
            throw new TransientHttpException('Fallo de red contactando Viafirma: ' . $e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode() ?? 0;
            if ($status >= 500 || $status === 429) {
                throw new TransientHttpException("Viafirma respondió {$status}.", $status, $e);
            }
            throw new ViafirmaClientException(
                "Viafirma respondió {$status} en getProfiles({$raCode}).",
                $status,
                $e,
            );
        }

        $raw = (string) $response->getBody();
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ViafirmaClientException('Respuesta de Viafirma no es JSON válido.');
        }

        $profiles = $this->parser->parse($decoded);
        $this->logger->info('viafirma.getProfiles.response', [
            'raCode' => $raCode,
            'count'  => count($profiles),
        ]);

        return $profiles;
    }
}

