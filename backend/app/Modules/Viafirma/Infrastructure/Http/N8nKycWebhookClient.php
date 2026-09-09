<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Http;

use App\Modules\Viafirma\Domain\Contracts\KycWebhookNotifierContract;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente del webhook de n8n (nodo "Webhook desde Laravel") que dispara el
 * aviso por WhatsApp (Evolution API) cuando se envía el correo de
 * verificación KYC pendiente a la empresa.
 *
 * Fire-and-forget: cualquier fallo (red, timeout, 4xx/5xx de n8n) se registra
 * como warning y NUNCA debe interrumpir el flujo que lo invoca — el correo ya
 * se envió correctamente independientemente de que este aviso falle.
 */
final class N8nKycWebhookClient implements KycWebhookNotifierContract
{
    public function __construct(
        private readonly SafePemLogger $logger,
    ) {}

    public function notify(string $casaSoftware, ?string $whatsapp, array $solicitudes, string $tipo): void
    {
        $url = (string) config('services.n8n.kyc_webhook_url', '');
        if ($url === '') {
            // Sin URL configurada, la integración queda apagada silenciosamente
            // (permite desplegar el código antes de tener el webhook de n8n activo).
            return;
        }

        if ($solicitudes === []) {
            return;
        }

        $normalizedWhatsapp = $this->normalizeWhatsapp($whatsapp);
        if ($normalizedWhatsapp === null) {
            $this->logger->warning('n8n.kyc_webhook.no_whatsapp', [
                'casa_software' => $casaSoftware,
                'solicitudes'   => $solicitudes,
            ]);
            return;
        }

        $payload = [
            'casa_software' => $casaSoftware,
            'whatsapp'      => $normalizedWhatsapp,
            'solicitudes'   => array_values($solicitudes),
            'count'         => count($solicitudes),
            'tipo'          => $tipo,
        ];

        try {
            $response = Http::timeout(10)->post($url, $payload);

            if ($response->failed()) {
                $this->logger->warning('n8n.kyc_webhook.non_2xx', [
                    'status'  => $response->status(),
                    'body'    => substr($response->body(), 0, 500),
                    'payload' => $payload,
                ]);
                return;
            }

            $this->logger->info('n8n.kyc_webhook.sent', [
                'casa_software' => $casaSoftware,
                'count'         => $payload['count'],
                'tipo'          => $tipo,
            ]);
        } catch (Throwable $e) {
            $this->logger->warning('n8n.kyc_webhook.failed', [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }

    /**
     * Normaliza a formato internacional sin `+` ni espacios, tal como exige
     * Evolution API. `companies.phone` se captura manualmente y llega con
     * formatos inconsistentes (espacios, guiones, puntos, paréntesis) — se
     * elimina cualquier carácter que no sea dígito antes de evaluar longitud.
     *
     * Ejemplos: "300-782.48.44" / "(300) 782-4844" / "300 782 4844" -> "573007824844"
     *
     * Si el número local colombiano trae 10 dígitos (sin indicativo), se le
     * antepone el indicativo de país (57). Si ya trae indicativo, se deja igual.
     */
    private function normalizeWhatsapp(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return '57' . $digits;
        }

        return $digits;
    }
}
