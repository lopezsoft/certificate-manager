-- =====================================================================
-- DDL: Agregar campo country_id a tabla certificate_requests
-- Fecha: 2026-07-09
-- Propósito: Vincular país directamente en solicitud (no vía company)
-- Default: 45 (Colombia)
-- =====================================================================

ALTER TABLE `certificate_requests`
ADD COLUMN `country_id` INT(11) NOT NULL DEFAULT 45
COMMENT 'ID del país (FK countries). Default: 45 = Colombia'
AFTER `company_id`;

-- Crear índice para búsquedas por país
ALTER TABLE `certificate_requests`
ADD INDEX `idx_certificate_requests_country_id` (`country_id`);

-- Agregar constraint de clave foránea
ALTER TABLE `certificate_requests`
ADD CONSTRAINT `fk_cr_country`
FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
ON UPDATE CASCADE
ON DELETE RESTRICT;

-- Verificación: validar que el campo fue creado correctamente
-- SELECT * FROM certificate_requests LIMIT 1;
-- SHOW CREATE TABLE certificate_requests;
