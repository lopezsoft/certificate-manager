-- =====================================================================
-- DDL: Agregar columna kyc_accreditation_link a viafirma_certificate_request_states
-- Fecha: 2026-07-09
-- Propósito: Persistir el link del portal KYC capturado automáticamente
-- =====================================================================

ALTER TABLE `viafirma_certificate_request_states`
    ADD COLUMN `kyc_accreditation_link` VARCHAR(500) COMMENT 'Link KYC del portal de acreditacion capturado cuando remote_status es accreditation' NULL
    AFTER `auto_redownload_attempts`;
