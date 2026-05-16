<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Exceptions;

/**
 * Error 5xx o de red: el caller puede reintentar con backoff.
 */
final class TransientHttpException extends ViafirmaClientException
{
}

