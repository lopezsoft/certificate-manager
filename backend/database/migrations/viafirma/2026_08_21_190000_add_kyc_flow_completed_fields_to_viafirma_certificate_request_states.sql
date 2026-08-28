-- Migración manual: viafirma_certificate_request_states
-- Corresponde a: 2026_08_21_190000_add_kyc_flow_completed_fields_to_viafirma_certificate_request_states.php
-- Motor destino: MariaDB 10.3
-- Ejecutar en un mantenimiento controlado.

START TRANSACTION;

ALTER TABLE `viafirma_certificate_request_states`
  ADD COLUMN `kyc_flow_completed_at` TIMESTAMP NULL DEFAULT NULL
    COMMENT 'Cuándo el navegador del cliente llegó al callback tras completar MetaMap. Señal de UX, no de aprobación real.'
    AFTER `kyc_accreditation_link`,
  ADD COLUMN `kyc_flow_completed_ip` VARCHAR(45) NULL DEFAULT NULL
    COMMENT 'IP del navegador en el callback (IPv4/IPv6).'
    AFTER `kyc_flow_completed_at`,
  ADD COLUMN `kyc_flow_completed_user_agent` VARCHAR(500) NULL DEFAULT NULL
    AFTER `kyc_flow_completed_ip`;

COMMIT;
