<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Contracts;

/**
 * Notifica al flujo de n8n (WhatsApp vía Evolution API) cada vez que se envía
 * el correo de "verificación de identidad pendiente" a la empresa dueña de
 * una solicitud — tanto en el envío inmediato (agrupado por ventana corta,
 * ver KycImmediateWebhookBatcher) como en el recordatorio diario.
 */
interface KycWebhookNotifierContract
{
    /**
     * @param array<int, array{codigo: string, enlace: string}> $solicitudes
     * @param string $tipo 'inmediato' o 'recordatorio'
     */
    public function notify(string $casaSoftware, ?string $whatsapp, array $solicitudes, string $tipo): void;
}
