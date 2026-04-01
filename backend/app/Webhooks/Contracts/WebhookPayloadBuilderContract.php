<?php

namespace App\Webhooks\Contracts;

interface WebhookPayloadBuilderContract
{
    /**
     * Tipo de evento que este builder sabe construir.
     */
    public function supports(): string;

    /**
     * Construye el payload JSON que recibirá el endpoint externo.
     */
    public function build(WebhookEventContract $event): array;
}
