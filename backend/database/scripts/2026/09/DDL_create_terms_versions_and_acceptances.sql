-- =====================================================================
-- DDL: Versionado de Términos y Condiciones y registro de aceptación
-- Fecha: 2026-09-11
-- Propósito: Evidencia legal (numeral 5 T&C MATICERTS) — qué versión de
--            los T&C aceptó cada usuario, cuándo, desde qué IP y para qué
--            solicitud. Las aceptaciones son INMUTABLES (append-only).
-- Fuente oficial del documento: https://maticerts.com/terminos/
-- Publicación de versiones: php artisan terms:publish --tag=1.0
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) terms_versions — cada publicación del documento
-- ---------------------------------------------------------------------
CREATE TABLE `terms_versions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version`       VARCHAR(20)  NOT NULL COMMENT 'Etiqueta de versión (ej. 1.0, 1.1)',
  `published_at`  DATETIME     NOT NULL COMMENT 'Fecha/hora de publicación (UTC)',
  `source_url`    VARCHAR(255) NOT NULL COMMENT 'URL pública del documento en el momento de publicar',
  `content_hash`  CHAR(64)     NOT NULL COMMENT 'SHA-256 del texto normalizado (content)',
  `content`       LONGTEXT     NOT NULL COMMENT 'Snapshot íntegro del texto aceptado (evidencia)',
  `is_current`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = versión vigente que el front debe mostrar',
  `created_at`    TIMESTAMP    NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_terms_versions_version` (`version`),
  KEY `idx_terms_versions_is_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de versiones publicadas de los Términos y Condiciones';

-- ---------------------------------------------------------------------
-- 2) terms_acceptances — evidencia de aceptación (append-only)
--    Polimórfica: hoy CertificateRequest; extensible a CertificateOrder.
--    consent_scope permite más de un consentimiento por solicitud
--    (T&C MATICERTS y, para Viafirma, la Política de Certificación).
-- ---------------------------------------------------------------------
CREATE TABLE `terms_acceptances` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `terms_version_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK terms_versions.id — versión aceptada',
  `user_id`          BIGINT UNSIGNED NOT NULL COMMENT 'FK users.id — quién aceptó',
  `company_id`       BIGINT          NOT NULL COMMENT 'FK companies.id — empresa del usuario',
  `acceptable_type`  VARCHAR(255)    NOT NULL COMMENT 'Modelo asociado (App\Models\CertificateRequest)',
  `acceptable_id`    BIGINT UNSIGNED NOT NULL COMMENT 'ID del modelo asociado',
  `consent_scope`    VARCHAR(60)     NOT NULL DEFAULT 'MATICERTS_TERMS_IMMEDIATE_EXECUTION'
                     COMMENT 'Alcance del consentimiento (ver App\Enums\TermsConsentScopeEnum)',
  `accepted_at`      DATETIME        NOT NULL COMMENT 'Fecha/hora de aceptación (UTC, capturada en servidor)',
  `ip_address`       VARCHAR(45)     NOT NULL COMMENT 'IP del cliente (IPv4/IPv6), capturada en servidor',
  `user_agent`       VARCHAR(512)    NULL COMMENT 'User-Agent del navegador',
  `created_at`       TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`       TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_terms_acceptances_target_scope` (`acceptable_type`, `acceptable_id`, `consent_scope`),
  KEY `idx_terms_acceptances_user_id` (`user_id`),
  KEY `idx_terms_acceptances_company_id` (`company_id`),
  KEY `idx_terms_acceptances_version_id` (`terms_version_id`),
  CONSTRAINT `fk_terms_acceptances_version`
    FOREIGN KEY (`terms_version_id`) REFERENCES `terms_versions` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_terms_acceptances_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Evidencia de aceptación de T&C por solicitud (inmutable)';

-- ---------------------------------------------------------------------
-- Post-DDL (manual, en servidor): publicar la versión vigente
--   php artisan terms:publish --tag=1.0
-- Verificación:
--   SELECT id, version, published_at, content_hash, is_current FROM terms_versions;
-- ---------------------------------------------------------------------
