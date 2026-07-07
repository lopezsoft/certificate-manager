ALTER TABLE `companies`
ADD COLUMN `uuid` VARCHAR(36) NULL DEFAULT NULL AFTER `id`,
ADD UNIQUE INDEX `uuid` (`uuid`);

UPDATE companies AS a
SET
    a.`uuid` = UUID ();

ALTER TABLE `companies`
ADD COLUMN `company_type_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `uuid`,
ADD COLUMN `has_agreement` TINYINT (1) NOT NULL DEFAULT '0' COMMENT 'Indica si la empresa tiene convenio POSTPAID' AFTER `company_type_id`,
ADD COLUMN `issuance_provider` VARCHAR(32) NULL DEFAULT NULL COMMENT 'Override del proveedor de emisión: mail|viafirma|null=default' AFTER `has_agreement`,
ADD INDEX `companies_company_type_id_foreign` (`company_type_id`) USING BTREE,
ADD CONSTRAINT `FK_companies_company_types` FOREIGN KEY (`company_type_id`) REFERENCES `company_types` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE `companies` ADD INDEX `idx_companies_issuance_provider` (`issuance_provider`);

UPDATE `companies` AS a
set
    a.`issuance_provider` = 'viafirma';

-- 1) Tabla de tipos de documento constitutivo
CREATE TABLE
    `entity_document_types` (
        `id` int (10) unsigned NOT NULL AUTO_INCREMENT,
        `code` varchar(10) NOT NULL COMMENT 'Código corto (CC, PJ, etc.)',
        `description` varchar(120) NOT NULL COMMENT 'Descripción legible',
        `active` tinyint (1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `entity_document_types_code_unique` (`code`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8;

-- 2) Seed inicial
INSERT INTO
    `entity_document_types` (`id`, `code`, `description`, `active`)
VALUES
    (1, 'CC', 'Cámara de Comercio', 1),
    (2, 'PJ', 'Personería Jurídica', 1);

-- 3) Nuevos campos en certificate_requests
ALTER TABLE `certificate_requests`
ADD COLUMN `entity_document_type_id` int (10) unsigned NOT NULL DEFAULT 1 COMMENT 'Tipo de documento constitutivo (FK entity_document_types). Default: 1 = Cámara de Comercio' AFTER `type_organization_id`,
ADD COLUMN `legal_rep_email` varchar(250) DEFAULT NULL COMMENT 'Email del representante legal para verificación Viafirma' AFTER `legal_representative`,
ADD KEY `fk_cr_entity_document_type` (`entity_document_type_id`),
ADD CONSTRAINT `fk_cr_entity_document_type` FOREIGN KEY (`entity_document_type_id`) REFERENCES `entity_document_types` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT;

-- 4) Nuevos campos para nombre del representante legal
ALTER TABLE `certificate_requests` ADD `legal_rep_first_name` VARCHAR(120) NULL AFTER `legal_representative`,
ADD `legal_rep_last_name` VARCHAR(120) NULL AFTER `legal_rep_first_name`;

ALTER TABLE `change_histories` CHANGE COLUMN `user_id` `user_id` BIGINT (20) UNSIGNED NULL DEFAULT NULL AFTER `certificate_request_id`;

ALTER TABLE `change_histories` CHANGE COLUMN `user_of_change` `user_of_change` ENUM ('USER', 'MANAGER', 'SYSTEM', 'PROVIDER') NOT NULL COLLATE 'utf8mb4_general_ci' AFTER `user_id`;