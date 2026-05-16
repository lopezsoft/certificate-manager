<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Exceptions;

use RuntimeException;

/**
 * Base exception para el módulo Viafirma. Todas las excepciones del módulo
 * deben extender de ésta para permitir captura uniforme por capa.
 */
class ViafirmaException extends RuntimeException
{
}

