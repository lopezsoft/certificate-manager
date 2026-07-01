ALTER TABLE `file_managers` CHANGE COLUMN `document_type` `document_type` ENUM (
  'ATTACHED',
  'CERTIFICATE',
  'PAYMENT',
  'P7B_CERTIFICATE',
  'PRIVATE_KEY'
) NOT NULL DEFAULT 'ATTACHED' COLLATE 'utf8_general_ci' AFTER `status`;

ALTER TABLE `file_managers` CHANGE COLUMN `uuid` `uuid` CHAR(36) NULL COLLATE 'utf8_general_ci' AFTER `id`;

ALTER TABLE `change_histories` CHANGE COLUMN `status` `status` ENUM (
  'DRAFT',
  'SENT',
  'CANCELLED',
  'REJECTED',
  'ON_HOLD',
  'DEFINITIVE',
  'CLOSED',
  'OPEN',
  'DELETED',
  'PENDING',
  'ACCEPTED',
  'PROCESSING',
  'PROCESSED',
  'UNKNOWN',
  'REVOKED',
  'EXPIRED'
) NOT NULL COLLATE 'utf8mb4_general_ci' AFTER `user_of_change`;