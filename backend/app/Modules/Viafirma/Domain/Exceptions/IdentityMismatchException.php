<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Exceptions;

/**
 * El certificado emitido por la CA no coincide con el titular solicitado en
 * el CSR original — típicamente un error de validación de identidad del
 * lado del proveedor (ej. aprobación de una biometría equivocada).
 *
 * Es un fallo TERMINAL y NO recuperable por reintento: volver a ensamblar el
 * mismo P7B siempre producirá el mismo certificado mal emitido. Requiere
 * revocación del certificado erróneo y una nueva solicitud (nuevo CSR).
 */
final class IdentityMismatchException extends ViafirmaException
{
    public function __construct(
        public readonly string $expectedIdentity,
        public readonly ?string $actualIdentity,
    ) {
        parent::__construct(sprintf(
            'El certificado emitido no coincide con el titular solicitado. Esperado: %s, recibido: %s.',
            $expectedIdentity,
            $actualIdentity ?? '(no disponible)',
        ));
    }
}
