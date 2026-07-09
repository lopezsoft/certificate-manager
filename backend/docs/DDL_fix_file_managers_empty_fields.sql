-- =====================================================================
-- DDL: Corregir document_type y status vacíos en file_managers
-- Fecha: 2026-07-09
-- Propósito: Llenar campos enum vacíos con valores correctos
-- =====================================================================

-- 1) Llenar document_type vacío para archivos P7B
UPDATE `file_managers`
SET `document_type` = 'P7B_CERTIFICATE'
WHERE `extension_file` = 'p7b'
AND (`document_type` IS NULL OR `document_type` = '');

-- 2) Llenar status vacío para archivos private_key_reference
UPDATE `file_managers`
SET `status` = 'COMPLETED'
WHERE `file_name` = 'private_key_reference'
AND (`status` IS NULL OR `status` = '');

-- 3) Llenar status vacío para archivos ZIP
UPDATE `file_managers`
SET `status` = 'COMPLETED'
WHERE `extension_file` = 'zip'
AND (`status` IS NULL OR `status` = '');

-- 4) Llenar document_type vacío para archivos ZIP
UPDATE `file_managers`
SET `document_type` = 'CERTIFICATE'
WHERE `extension_file` = 'zip'
AND (`document_type` IS NULL OR `document_type` = '');

-- Verificación: confirmar que no hay más campos vacíos
-- SELECT id, file_name, extension_file, document_type, status
-- FROM file_managers
-- WHERE (document_type IS NULL OR document_type = '')
--    OR (status IS NULL OR status = '');
