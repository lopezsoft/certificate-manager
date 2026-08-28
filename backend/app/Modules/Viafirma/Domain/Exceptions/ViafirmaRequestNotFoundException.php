<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Exceptions;

/**
 * Viafirma respondió 404 (`errorCode: request_not_found`) — el `codRequest`
 * ya no existe de su lado. Ocurre típicamente en el sandbox de Viafirma, que
 * purga periódicamente solicitudes de prueba.
 *
 * Es un fallo TERMINAL y NO recuperable por reintento: el recurso nunca va a
 * volver a existir. Reintentar indefinidamente (como hacía el hook `failed()`
 * antes de este fix) desperdicia ciclos de cola sin ningún resultado posible.
 */
final class ViafirmaRequestNotFoundException extends ViafirmaClientException
{
}
