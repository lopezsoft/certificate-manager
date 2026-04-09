# Directorio de inicialización de la base de datos

Cualquier archivo `.sql` o `.sql.gz` colocado aquí se ejecutará **automáticamente** 
la primera vez que se cree el volumen `mariadb_data` (es decir, cuando el contenedor 
de MariaDB arranca por primera vez con un volumen vacío).

## Para importar el dump de producción de Laravel:

1. Exportar la BD de producción:
   ```bash
   mysqldump --single-transaction --routines --triggers \
     -u root -p nombre_base_datos > 001-dump-produccion.sql
   ```

2. Colocar el archivo aquí:
   ```
   docker/initdb/001-dump-produccion.sql
   ```

3. Arrancar los servicios:
   ```bash
   docker compose up -d
   ```

> **Nota:** Los archivos se ejecutan en orden alfabético. Por eso se recomienda 
> usar prefijos numéricos (`001-`, `002-`, etc.) si necesitas ejecutar múltiples scripts.

> **⚠️ Importante:** Este directorio se monta como `read-only` (:ro) en el contenedor.
> Los archivos aquí **NO se versionan en Git** (ver `.gitignore`).

