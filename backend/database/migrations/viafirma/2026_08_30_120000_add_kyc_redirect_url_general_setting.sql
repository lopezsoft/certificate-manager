-- Registra el nuevo setting global "VIAFIRMA_KYC_REDIRECT_URL", que permite
-- a cada empresa sobreescribir (vía general_setting_companies, UI genérica
-- de configuración por empresa ya existente) a dónde se redirige al cliente
-- tras completar la verificación de identidad KYC (MetaMap).
--
-- Sin override por empresa, ViafirmaKycCallbackController usa el default
-- global (FRONTEND_URL + VIAFIRMA_KYC_COMPLETED_PATH del .env) — por eso el
-- `value` del catálogo global se deja VACÍO/NULL a propósito: no se necesita
-- guardar ahí la URL por defecto (ya vive en config/viafirma.php + .env), y
-- la columna `value` de general_settings no tiene espacio suficiente para
-- una URL completa (confirmado: "Data too long" al intentarlo). No se altera
-- la tabla — el override real por empresa se guarda en
-- general_setting_companies.value (VARCHAR(250), sí alcanza).
--
-- ⚠️ AJUSTAR el valor de `tag` antes de ejecutar — usar la misma categoría
-- que agrupe settings relacionados con Viafirma/certificados si ya existe,
-- o la que el equipo considere apropiada (se dejó como placeholder abajo).

START TRANSACTION;

INSERT INTO `general_settings`
  (`tag`, `key_value`, `data_type`, `value`, `list_values`, `min_value`, `max_value`, `description`, `tooltip`, `active`)
VALUES
  (
    1, -- ⚠️ CONFIRMAR/AJUSTAR: id de categoría (tag) apropiado
    'VIAFIRMA_KYC_REDIRECT_URL',
    'V',
    NULL,
    '',
    0,
    0,
    'URL de redirección tras verificación KYC',
    'URL a la que se redirige al cliente tras completar la verificación de identidad (MetaMap). Vacío = usar la página por defecto del sistema.',
    1
  );

COMMIT;
