<?php

namespace App\Webhooks\Contracts;

interface WebhookEventContract
{
    /**
     * Tipo de evento, ej: "certificate_request.created"
     */
    public function eventType(): string;

    /**
     * ID de la compañía propietaria del recurso.
     * Permite filtrar los endpoints correctos en multi-tenant.
     */
    public function companyId(): int;

    /**
     * Datos del recurso afectado (sin transformar).
     */
    public function resourceData(): array;
}
