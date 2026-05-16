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

