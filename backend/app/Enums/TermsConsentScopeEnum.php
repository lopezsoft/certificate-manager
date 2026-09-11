<?php

namespace App\Enums;

/**
 * Alcance de cada consentimiento registrado en terms_acceptances.
 *
 * Una misma solicitud puede tener más de un consentimiento (uno por scope).
 */
enum TermsConsentScopeEnum: string
{
    /**
     * Aceptación de los T&C de MATICERTS incluyendo la autorización expresa
     * de ejecución inmediata del servicio (validación de identidad + emisión)
     * y la renuncia al retracto sobre ese servicio (cláusula 5 de los T&C).
     */
    case MATICERTS_TERMS_IMMEDIATE_EXECUTION = 'MATICERTS_TERMS_IMMEDIATE_EXECUTION';

    /**
     * Reservado: aceptación de la Política de Servicios de Certificación de
     * Viafirma (PDS). Hoy el front la muestra pero no se persiste.
     */
    case VIAFIRMA_CERTIFICATION_POLICY = 'VIAFIRMA_CERTIFICATION_POLICY';
}
