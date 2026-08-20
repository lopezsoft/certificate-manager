-- Migración manual: viafirma_status_history
-- Corresponde a: 2026_08_19_190000_add_created_at_and_poll_count_to_viafirma_status_history.php
-- Motor destino: MariaDB 10.3
-- Ejecutar en un mantenimiento controlado. Tabla actual: ~2,100 filas (AUTO_INCREMENT=2101), ALTER rápido.

START TRANSACTION;

ALTER TABLE `viafirma_status_history`
  ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    COMMENT 'Momento en que inicia el episodio de estado. No se actualiza tras el INSERT.'
    AFTER `attempt_number`,
  ADD COLUMN `poll_count_in_state` INT(10) UNSIGNED NOT NULL DEFAULT 1
    COMMENT 'Cantidad de polls que confirmaron este mismo estado sin cambios.'
    AFTER `created_at`;

-- Backfill para filas existentes: usar `occurred_at` como mejor aproximación de
-- `created_at`, ya que bajo el comportamiento anterior cada fila SÍ representaba
-- un INSERT individual por poll (no episodios agrupados). Sin este paso, todas
-- las filas históricas quedarían con created_at = momento del ALTER.
UPDATE `viafirma_status_history` SET `created_at` = `occurred_at`;

COMMIT;

-- Verificación post-migración (opcional):
-- SELECT COUNT(*) AS total, MIN(created_at) AS created_min, MAX(created_at) AS created_max,
--        AVG(poll_count_in_state) AS avg_poll_count
-- FROM viafirma_status_history;
