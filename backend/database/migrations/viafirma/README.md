# Migraciones del módulo Viafirma — POLÍTICA §10.bis del roadmap

> 🚫 **No usar `php artisan migrate` global en este proyecto.**
>
> Las migraciones de este directorio NO se aplicarán de forma automática.
> Cada migración se ejecuta **manualmente** y **una por una** a través del
> wrapper seguro:
>
> ```bash
> # 1) Dry-run (por defecto)
> php artisan viafirma:migrate 2026_05_14_100001_create_viafirma_certificate_requests_table.php
>
> # 2) Aplicar realmente
> php artisan viafirma:migrate 2026_05_14_100001_create_viafirma_certificate_requests_table.php --apply
>
> # 3) Aplicar en staging/producción (requiere --force)
> php artisan viafirma:migrate 2026_05_14_100001_create_viafirma_certificate_requests_table.php --apply --force
>
> # 4) Rollback puntual
> php artisan viafirma:migrate 2026_05_14_100001_create_viafirma_certificate_requests_table.php --rollback --apply
> ```
>
> Estado:
> ```bash
> php artisan viafirma:migrate:status
> ```
>
> Tras cada ejecución registrar en `CHANGELOG.md` con fecha, entorno y autor.

## Pendientes de ejecución manual

- **`2026_08_19_190000_add_created_at_and_poll_count_to_viafirma_status_history.php`**
  Agrega `created_at` (fijo, inicio del episodio de estado) y `poll_count_in_state`
  (confirmaciones sin cambio) a `viafirma_status_history`. Incluye backfill
  (`created_at = occurred_at` para filas existentes).
  **Producción corre MariaDB 10.3** — usar el DDL manual equivalente en
  `2026_08_19_190000_add_created_at_and_poll_count_to_viafirma_status_history.sql`
  (mismo directorio) en vez de este archivo si se aplica directo por consola SQL.
  ⚠️ El código de la app (`StateMachine::touchCurrentHistoryRow()`) ya asume que
  estas columnas existen — aplicar la migración **antes** de desplegar ese código.

- **`2026_08_21_190000_add_kyc_flow_completed_fields_to_viafirma_certificate_request_states.php`**
  Agrega `kyc_flow_completed_at`, `kyc_flow_completed_ip`, `kyc_flow_completed_user_agent`
  a `viafirma_certificate_request_states` — registran cuándo el navegador del
  cliente llegó al nuevo callback público tras completar MetaMap (señal de UX,
  NO de aprobación real de Viafirma).
  **Producción corre MariaDB 10.3** — usar el DDL manual equivalente en
  `2026_08_21_190000_add_kyc_flow_completed_fields_to_viafirma_certificate_request_states.sql`.
  ⚠️ `RecordKycFlowCompletedUseCase` ya asume que estas columnas existen —
  aplicar la migración **antes** de desplegar ese código.

## Cambios de código relacionados (sin migración nueva)

Sesión 2026-08-19 también corrigió, sin requerir cambios de esquema adicionales:
eliminación de expiración automática del polling, auto-reparación ante mutex/fallos,
ampliación de la captura del link KYC a toda la familia `accreditation*`, correo
automático a la empresa dueña de la solicitud, y el paso `accreditation` agregado
a `MockViafirmaClient` para sandbox. Detalle completo en `docs/CHANGELOG.md`.
