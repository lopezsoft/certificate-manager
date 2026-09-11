/**
 * Interfaces para el endpoint público GET /terms/current y la evidencia de
 * aceptación de Términos y Condiciones que expone el detalle de solicitud.
 */

/** Versión vigente de los Términos y Condiciones de MATICERTS. */
export interface TermsVersion {
  id: number;
  version: string;
  published_at: string;
  source_url: string;
  content_hash: string;
}

/** Evidencia de aceptación registrada por el backend en cada solicitud. */
export interface TermsAcceptance {
  accepted_at: string;
  ip_address: string;
  consent_scope: string;
  terms_version: TermsVersion;
}
