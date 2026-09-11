-- Migración manual: viafirma_certificate_request_states
-- Corresponde a: 2026_09_10_090000_add_kyc_last_call_sent_at_to_viafirma_certificate_request_states.php
-- Motor destino: MariaDB 10.3
-- Ejecutar en un mantenimiento controlado.
--
-- Marca cuándo se envió el aviso de "último llamado" (24h antes del
-- vencimiento del plazo de verificación KYC), para que el cron horario
-- ExpireStalledKycAccreditationsJob no lo reenvíe en cada corrida dentro
-- de la misma ventana de 24h.

START TRANSACTION;

ALTER TABLE `viafirma_certificate_request_states`
  ADD COLUMN `kyc_last_call_sent_at` TIMESTAMP NULL DEFAULT NULL
    COMMENT 'Cuándo se envió el aviso de último llamado (24h antes de expires_at). NULL = aún no enviado.'
    AFTER `kyc_flow_completed_user_agent`;

COMMIT;
