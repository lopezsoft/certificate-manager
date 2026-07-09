<?php

declare(strict_types=1);

namespace App\Exceptions\Certificate;

use RuntimeException;

/**
 * Señala que un CertificateRequest llegó al pipeline de emisión con datos
 * estructuralmente inválidos que debieron ser rechazados en
 * CreateCertificateRequestFormRequest al momento de la creación.
 *
 * Su sola aparición indica un bug de validación en el boundary HTTP, nunca
 * un escenario de negocio legítimo. No se reintenta: el job se marca como
 * fallido de inmediato vía $this->fail($e).
 */
class CertificateDataIntegrityException extends RuntimeException
{
}
