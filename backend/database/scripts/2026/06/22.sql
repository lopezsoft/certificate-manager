ALTER TABLE `file_managers` CHANGE COLUMN `document_type` `document_type` ENUM (
  'ATTACHED',
  'CERTIFICATE',
  'PAYMENT',
  'P7B_CERTIFICATE',
  'PRIVATE_KEY'
) NOT NULL DEFAULT 'ATTACHED' COLLATE 'utf8_general_ci' AFTER `status`;