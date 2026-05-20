<?php

declare(strict_types=1);

namespace App\DTOs\Certificate;

/**
 * Resultado normalizado de cualquier proveedor de emisión de certificados.
 *
 * Su forma es deliberadamente plana para serializarse directamente en JSON
 * sin necesidad de transformadores específicos por proveedor.
 */
final class IssuanceResult
{
    public const STATUS_SENT        = 'sent';         // Mail: enviado a la autoridad
    public const STATUS_SUBMITTED   = 'submitted';    // Viafirma: aceptado por RA
    public const STATUS_PROCESSING  = 'processing';   // Trámite en curso (polling)
    public const STATUS_READY       = 'ready';        // Listo para descarga
    public const STATUS_COMPLETED   = 'completed';    // Emitido y entregado
    public const STATUS_FAILED      = 'failed';       // Error definitivo
    public const STATUS_UNSUPPORTED = 'unsupported';  // Operación no soportada

    /**
     * @param array<string,mixed> $data Payload específico del proveedor (read-only
     *        para el cliente). Ejemplo Viafirma: cod_request, public_id, internal_state.
     */
    public function __construct(
        public readonly string  $providerName,
        public readonly string  $status,
        public readonly string  $message,
        public readonly ?string $externalId   = null,
        public readonly ?int    $resourceId   = null,
        public readonly int     $httpStatus   = 200,
        public readonly array   $data         = [],
    ) {}

    public function isSuccess(): bool
    {
        return !in_array($this->status, [self::STATUS_FAILED, self::STATUS_UNSUPPORTED], true);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'provider'    => $this->providerName,
            'status'      => $this->status,
            'message'     => $this->message,
            'external_id' => $this->externalId,
            'resource_id' => $this->resourceId,
            'data'        => $this->data,
        ];
    }
}

