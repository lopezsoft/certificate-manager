ALTER TABLE `companies`
    ADD COLUMN `uuid` VARCHAR(36) NULL DEFAULT NULL AFTER `id`,
    ADD UNIQUE INDEX `uuid` (`uuid`);
UPDATE companies AS a SET a.`uuid` = UUID();
