#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# Script de Migración: Laravel → NestJS Dockerizado
# Certificate Manager
#
# USO:
#   chmod +x scripts/migrate-from-laravel.sh
#   ./scripts/migrate-from-laravel.sh \
#     --laravel-path /var/www/certificate-manager/backend \
#     --db-user root \
#     --db-pass secret \
#     --db-name certificate_manager
#
# Este script:
#   1. Exporta la BD de producción de Laravel (mysqldump)
#   2. Copia los directorios de storage (attachments + pdf)
#   3. Coloca el dump en docker/initdb/ para importación automática
#   4. Valida la estructura de archivos
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Colores ──
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No color

# ── Valores por defecto ──
LARAVEL_PATH=""
DB_USER="root"
DB_PASS=""
DB_NAME=""
DB_HOST="127.0.0.1"
DB_PORT="3306"
SKIP_DB=false
SKIP_FILES=false
NEST_PATH="$(cd "$(dirname "$0")/.." && pwd)"

# ── Parseo de argumentos ──
while [[ $# -gt 0 ]]; do
  case $1 in
    --laravel-path) LARAVEL_PATH="$2"; shift 2 ;;
    --db-user)      DB_USER="$2";      shift 2 ;;
    --db-pass)      DB_PASS="$2";      shift 2 ;;
    --db-name)      DB_NAME="$2";      shift 2 ;;
    --db-host)      DB_HOST="$2";      shift 2 ;;
    --db-port)      DB_PORT="$2";      shift 2 ;;
    --skip-db)      SKIP_DB=true;      shift   ;;
    --skip-files)   SKIP_FILES=true;   shift   ;;
    -h|--help)
      echo "Uso: $0 --laravel-path /ruta/al/backend --db-user root --db-pass secret --db-name mi_db"
      echo ""
      echo "Opciones:"
      echo "  --laravel-path   Ruta al proyecto Laravel (backend/)"
      echo "  --db-user        Usuario de la BD (default: root)"
      echo "  --db-pass        Contraseña de la BD"
      echo "  --db-name        Nombre de la BD"
      echo "  --db-host        Host de la BD (default: 127.0.0.1)"
      echo "  --db-port        Puerto de la BD (default: 3306)"
      echo "  --skip-db        Omitir la exportación de la BD"
      echo "  --skip-files     Omitir la copia de archivos"
      exit 0
      ;;
    *) echo -e "${RED}Argumento desconocido: $1${NC}"; exit 1 ;;
  esac
done

# ── Validaciones ──
if [[ -z "$LARAVEL_PATH" ]]; then
  echo -e "${RED}Error: --laravel-path es obligatorio${NC}"
  exit 1
fi

if [[ ! -d "$LARAVEL_PATH/storage/app" ]]; then
  echo -e "${RED}Error: No se encontró storage/app en $LARAVEL_PATH${NC}"
  exit 1
fi

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Migración Laravel → NestJS (Certificate Manager)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "  Laravel path:  ${YELLOW}$LARAVEL_PATH${NC}"
echo -e "  NestJS path:   ${YELLOW}$NEST_PATH${NC}"
echo -e "  Skip DB:       $SKIP_DB"
echo -e "  Skip Files:    $SKIP_FILES"
echo ""

# ══════════════════════════════════════════════════════════════════════════════
# PASO 1: Exportar BD
# ══════════════════════════════════════════════════════════════════════════════
if [[ "$SKIP_DB" == "false" ]]; then
  if [[ -z "$DB_NAME" ]]; then
    echo -e "${RED}Error: --db-name es obligatorio (o use --skip-db)${NC}"
    exit 1
  fi

  echo -e "${GREEN}[1/4] Exportando base de datos...${NC}"
  DUMP_FILE="$NEST_PATH/docker/initdb/001-${DB_NAME}-$(date +%Y%m%d%H%M%S).sql"

  mysqldump \
    --single-transaction \
    --routines \
    --triggers \
    --add-drop-table \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    "$DB_NAME" > "$DUMP_FILE"

  DUMP_SIZE=$(du -h "$DUMP_FILE" | cut -f1)
  echo -e "  Dump creado: ${YELLOW}$DUMP_FILE${NC} ($DUMP_SIZE)"
  echo -e "  ${GREEN}✓ BD exportada correctamente${NC}"
else
  echo -e "${YELLOW}[1/4] Exportación de BD omitida (--skip-db)${NC}"
fi

# ══════════════════════════════════════════════════════════════════════════════
# PASO 2: Copiar archivos de storage
# ══════════════════════════════════════════════════════════════════════════════
if [[ "$SKIP_FILES" == "false" ]]; then
  echo -e "${GREEN}[2/4] Copiando archivos de storage...${NC}"

  # Crear directorios destino
  mkdir -p "$NEST_PATH/storage/app/attachments"
  mkdir -p "$NEST_PATH/storage/app/pdf"

  # Copiar attachments
  if [[ -d "$LARAVEL_PATH/storage/app/attachments" ]]; then
    echo -e "  Copiando attachments..."
    rsync -av --progress \
      "$LARAVEL_PATH/storage/app/attachments/" \
      "$NEST_PATH/storage/app/attachments/"
    ATT_COUNT=$(find "$NEST_PATH/storage/app/attachments" -type f | wc -l)
    echo -e "  ${GREEN}✓ $ATT_COUNT archivos copiados en attachments/${NC}"
  else
    echo -e "  ${YELLOW}⚠ No se encontró storage/app/attachments en Laravel${NC}"
  fi

  # Copiar PDF
  if [[ -d "$LARAVEL_PATH/storage/app/pdf" ]]; then
    echo -e "  Copiando PDFs..."
    rsync -av --progress \
      "$LARAVEL_PATH/storage/app/pdf/" \
      "$NEST_PATH/storage/app/pdf/"
    PDF_COUNT=$(find "$NEST_PATH/storage/app/pdf" -type f | wc -l)
    echo -e "  ${GREEN}✓ $PDF_COUNT archivos copiados en pdf/${NC}"
  else
    echo -e "  ${YELLOW}⚠ No se encontró storage/app/pdf en Laravel${NC}"
  fi
else
  echo -e "${YELLOW}[2/4] Copia de archivos omitida (--skip-files)${NC}"
fi

# ══════════════════════════════════════════════════════════════════════════════
# PASO 3: Validar estructura
# ══════════════════════════════════════════════════════════════════════════════
echo -e "${GREEN}[3/4] Validando estructura de archivos...${NC}"

ERRORS=0

# Verificar que docker-compose.yml existe
if [[ ! -f "$NEST_PATH/docker-compose.yml" ]]; then
  echo -e "  ${RED}✗ No se encontró docker-compose.yml${NC}"
  ERRORS=$((ERRORS + 1))
fi

# Verificar directorios de storage
for DIR in "storage/app/attachments" "storage/app/pdf"; do
  if [[ -d "$NEST_PATH/$DIR" ]]; then
    echo -e "  ${GREEN}✓ $DIR existe${NC}"
  else
    echo -e "  ${RED}✗ $DIR no existe${NC}"
    ERRORS=$((ERRORS + 1))
  fi
done

# Verificar que hay archivos
ATT_COUNT=$(find "$NEST_PATH/storage/app/attachments" -type f 2>/dev/null | wc -l)
PDF_COUNT=$(find "$NEST_PATH/storage/app/pdf" -type f 2>/dev/null | wc -l)
echo -e "  Archivos en attachments: ${YELLOW}$ATT_COUNT${NC}"
echo -e "  Archivos en pdf:         ${YELLOW}$PDF_COUNT${NC}"

# Verificar estructura de carpetas companies/
if [[ -d "$NEST_PATH/storage/app/attachments/companies" ]]; then
  COMPANY_DIRS=$(find "$NEST_PATH/storage/app/attachments/companies" -mindepth 1 -maxdepth 1 -type d | wc -l)
  echo -e "  Empresas con archivos:   ${YELLOW}$COMPANY_DIRS${NC}"
  echo -e "  ${GREEN}✓ Estructura companies/ detectada correctamente${NC}"
else
  echo -e "  ${YELLOW}⚠ No se detectó estructura companies/ (puede ser normal si no hay archivos)${NC}"
fi

if [[ $ERRORS -gt 0 ]]; then
  echo -e "\n  ${RED}✗ Se encontraron $ERRORS errores. Revise los mensajes anteriores.${NC}"
  exit 1
fi

# ══════════════════════════════════════════════════════════════════════════════
# PASO 4: Resumen y próximos pasos
# ══════════════════════════════════════════════════════════════════════════════
echo -e "${GREEN}[4/4] Migración de datos completada ✓${NC}"
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Próximos pasos:${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "  1. Configurar ${YELLOW}.env${NC} con las variables de producción"
echo -e "     (ver .env.example para mapeo Laravel → NestJS)"
echo ""
echo -e "  2. Levantar el stack:"
echo -e "     ${YELLOW}docker compose up -d${NC}"
echo ""
echo -e "  3. Verificar la importación de la BD:"
echo -e "     ${YELLOW}docker exec -it backnest-mariadb mariadb -u root -p\$DB_PASSWORD \$DB_DATABASE -e 'SHOW TABLES;'${NC}"
echo ""
echo -e "  4. Verificar archivos (smoke test):"
echo -e "     ${YELLOW}curl -I http://localhost:3000/attachments/companies/1/2025/04/100092351/doc.pdf${NC}"
echo ""
echo -e "  5. Si la BD ya existía y necesitas importar manualmente:"
echo -e "     ${YELLOW}docker exec -i backnest-mariadb mariadb -u root -p\$DB_PASSWORD \$DB_DATABASE < docker/initdb/001-dump.sql${NC}"
echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════════════════${NC}"

