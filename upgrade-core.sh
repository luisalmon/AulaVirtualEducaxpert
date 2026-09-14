#!/usr/bin/env bash
#
# upgrade-core.sh — Actualiza el NÚCLEO de Moodle (estructura "public/" de 5.x)
# conservando los plugins propios de EducaXpert y la configuración.
# Antes de tocar nada, respalda en backup/<fecha_hora>/ todo lo que ya exista:
# la BD (mysqldump comprimido) y moodledata (zip/tar.gz). El código no hace
# falta respaldarlo aparte: el árbol siempre está limpio y commiteado antes
# de correr esto (lo exige el paso 0), así que 'git log' / 'git checkout
# <commit>' ya recuperan ese mismo estado. En una instalación nueva se omite
# cada pieza que no exista. SKIP_DATA_BACKUP=1 salta moodledata (deja solo
# la BD, para iterar rápido en local). La MIGRACIÓN de la BD (el upgrade en
# sí) la sigue haciendo 'php admin/cli/upgrade.php' después, no este script.
#
# La primera vez migra el repo de la estructura plana 4.5 a la split 5.x:
#   <repo>/            -> admin/cli, lib (shim), scripts, config-dist.php ...
#   <repo>/public/     -> la aplicación web + los plugins propios
#   <repo>/config.php  -> config env-based (se mantiene en la raíz, fuera del webroot)
#
# Uso:
#   git checkout -b upgrade/moodle-502
#   ./upgrade-core.sh 502
#   MOODLE_ZIP=/ruta/moodle-latest-502.zip ./upgrade-core.sh 502
#   SKIP_DATA_BACKUP=1 ./upgrade-core.sh 502   # sin zip de moodledata/código (BD sí se respalda)
#
set -euo pipefail

STABLE="${1:?Uso: ./upgrade-core.sh <stableNNN>   (p.ej. 502 para Moodle 5.2)}"
REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# Plugins NO-core (ruta relativa dentro de la app). Se guardan y se recolocan
# bajo public/ encima del núcleo nuevo.
KEEP_PLUGINS=(
  theme/moove
  theme/trema
  mod/customcert
  mod/hvp
  auth/userkey
  course/format/remuiformat
  question/format/h5p
  blocks/senceluisalmon
)
# Ficheros de la raíz del repo que nunca se tocan.
# vendor/ NO está aquí a propósito: cada versión de Moodle trae su propio
# composer.json/composer.lock con dependencias distintas, así que el viejo
# se borra con el resto del árbol y se regenera después con composer.phar
# (ver el aviso en "SIGUIENTES PASOS").
KEEP_FILES=(.git .gitignore .env .env.example config.php config-local.php
            README.md INSTALL.md upgrade-core.sh composer.phar fonts)

cd "$REPO"
echo "==> Repositorio: $REPO"

# 0 · Seguridad --------------------------------------------------------------
[ -z "$(git status --porcelain)" ] || { echo "!! Hay cambios sin commitear. Aborta." >&2; exit 1; }
BRANCH="$(git branch --show-current)"
case "$BRANCH" in
  upgrade/*) : ;;
  *) echo "!! Ponte primero en una rama:  git checkout -b upgrade/moodle-$STABLE" >&2; exit 1 ;;
esac
echo "==> Rama: $BRANCH"

if [ -d "$REPO/public/lib" ]; then
  SRC_BASE="public"; echo "==> Estructura actual: split (public/)"
else
  SRC_BASE="."; echo "==> Estructura actual: plana -> se migrará a split"
fi

# 1 · Respaldo antes de tocar nada (BD + moodledata + código actual) ------
# Todo lo que exista se junta en backup/<fecha_hora>/. Lo que no exista
# (instalación nueva, o MOODLE_DATAROOT sin definir) se omite con un aviso.
# SKIP_DATA_BACKUP=1 salta moodledata/código (deja solo la BD) para iterar
# rápido en local; la BD nunca se salta por esa variable.
env_get() {
  local key="$1" val
  val="$(grep -E "^${key}=" "$REPO/.env" 2>/dev/null | tail -1 | cut -d= -f2-)"
  val="${val%% #*}"                              # corta comentario en linea
  if [ "${val:0:1}" = '"' ] && [ "${val: -1}" = '"' ]; then val="${val:1:-1}"; fi
  if [ "${val:0:1}" = "'" ] && [ "${val: -1}" = "'" ]; then val="${val:1:-1}"; fi
  printf '%s' "$val"
}

# archive_dir <carpeta> <salida-sin-extension> [patrones-a-excluir...]
# Comprime con zip si está disponible; si no, cae a tar.gz. Imprime la ruta
# final por stdout (para capturarla con $(...)).
archive_dir() {
  local src="$1" out="$2" base; shift 2
  base="$(basename "$src")"
  if command -v zip >/dev/null 2>&1; then
    local zargs=()
    for pat in "$@"; do zargs+=(-x "${base}/${pat}"); done
    ( cd "$(dirname "$src")" && zip -rq "${out}.zip" "$base" "${zargs[@]}" )
    printf '%s' "${out}.zip"
  else
    local targs=()
    for pat in "$@"; do targs+=(--exclude="${base}/${pat}"); done
    tar czf "${out}.tgz" "${targs[@]}" -C "$(dirname "$src")" "$base"
    printf '%s' "${out}.tgz"
  fi
}

BACKDIR="$REPO/backup/$(date +%Y%m%d_%H%M%S)"
DID_BACKUP=0

# 1a) Base de datos --------------------------------------------------------
if [ ! -f "$REPO/.env" ]; then
  echo "==> Sin .env: se omite el respaldo de BD (no hay conexión configurada)"
elif ! command -v mysql >/dev/null 2>&1 || ! command -v mysqldump >/dev/null 2>&1; then
  echo "==> mysql/mysqldump no disponibles: se omite el respaldo de BD"
else
  DBTYPE="$(env_get MOODLE_DB_TYPE)"; DBTYPE="${DBTYPE:-mariadb}"
  DBNAME="$(env_get MOODLE_DB_NAME)"
  DBUSER="$(env_get MOODLE_DB_USER)"
  DBPASS="$(env_get MOODLE_DB_PASS)"
  DBHOST="$(env_get MOODLE_DB_HOST)"; DBHOST="${DBHOST:-127.0.0.1}"
  DBPORT="$(env_get MOODLE_DB_PORT)"

  if [ "$DBTYPE" = "pgsql" ]; then
    echo "==> dbtype=pgsql: el respaldo automático solo cubre mysql/mariadb; hazlo con pg_dump"
  elif [ -z "$DBNAME" ] || [ -z "$DBUSER" ]; then
    echo "==> .env sin MOODLE_DB_NAME/MOODLE_DB_USER: se omite el respaldo de BD"
  else
    MYSQL_ARGS=(-h "$DBHOST" -u "$DBUSER")
    [ -n "$DBPORT" ] && MYSQL_ARGS+=(-P "$DBPORT")
    [ -n "$DBPASS" ] && MYSQL_ARGS+=("-p${DBPASS}")

    TBLCOUNT="$(mysql "${MYSQL_ARGS[@]}" -Nse \
      "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DBNAME}'" 2>/dev/null)"
    case "$TBLCOUNT" in ''|*[!0-9]*) TBLCOUNT=0 ;; esac

    if [ "$TBLCOUNT" -gt 0 ]; then
      mkdir -p "$BACKDIR"
      echo "==> Respaldando BD '$DBNAME' ($TBLCOUNT tablas)"
      mysqldump "${MYSQL_ARGS[@]}" --single-transaction --no-tablespaces --routines --triggers "$DBNAME" \
        | gzip > "$BACKDIR/${DBNAME}.sql.gz"
      echo "    $(du -h "$BACKDIR/${DBNAME}.sql.gz" | cut -f1)  ${BACKDIR}/${DBNAME}.sql.gz"
      DID_BACKUP=1
    else
      echo "==> BD '$DBNAME' no existe o no tiene tablas (instalación nueva): se omite el respaldo"
    fi
  fi
fi

# 1b) moodledata ------------------------------------------------------------
if [ -n "${SKIP_DATA_BACKUP:-}" ]; then
  echo "==> SKIP_DATA_BACKUP=1: se omite el respaldo de moodledata"
else
  DATAROOT="$(env_get MOODLE_DATAROOT)"
  if [ -n "$DATAROOT" ] && [ -d "$DATAROOT" ] && [ -n "$(ls -A "$DATAROOT" 2>/dev/null)" ]; then
    mkdir -p "$BACKDIR"
    echo "==> Comprimiendo moodledata ($DATAROOT) — puede tardar según su tamaño"
    ARCHIVE="$(archive_dir "$DATAROOT" "$BACKDIR/moodledata")"; rc=$?
    if [ "$rc" -eq 0 ] && [ -n "$ARCHIVE" ] && [ -f "$ARCHIVE" ]; then
      echo "    $(du -h "$ARCHIVE" | cut -f1)  $ARCHIVE"
      DID_BACKUP=1
    else
      echo "!! No se pudo comprimir moodledata (¿espacio en disco?); continúo sin ese respaldo" >&2
    fi
  else
    echo "==> MOODLE_DATAROOT no definido/inexistente/vacío: se omite el respaldo de moodledata"
  fi
fi

if [ "$DID_BACKUP" -eq 1 ]; then
  echo "==> Respaldo en: $BACKDIR"
else
  rmdir "$BACKDIR" 2>/dev/null || true
  echo "==> Nada que respaldar (instalación nueva)"
fi

# 2 · Obtener el núcleo -----------------------------------------------------
if [ -n "${MOODLE_ZIP:-}" ]; then
  ZIP="$MOODLE_ZIP"; echo "==> Zip local: $ZIP"
else
  ZIP="$STAGE/moodle-latest-$STABLE.zip"
  BASE="https://packaging.moodle.org/stable$STABLE"
  echo "==> Descargando $BASE/moodle-latest-$STABLE.zip"
  curl -fL --retry 3 --connect-timeout 20 --progress-bar -o "$ZIP" "$BASE/moodle-latest-$STABLE.zip"
  if curl -fsL --connect-timeout 20 -o "$ZIP.sha256" "$BASE/moodle-latest-$STABLE.zip.sha256"; then
    want="$(grep -oiE '[0-9a-f]{64}' "$ZIP.sha256" | head -1 | tr 'A-F' 'a-f')"
    got="$(sha256sum "$ZIP" | cut -d' ' -f1)"
    if [ -n "$want" ] && [ "$want" = "$got" ]; then
      echo "==> SHA256 OK ($got)"
    else
      echo "!! SHA256 NO coincide  (esperado: $want / obtenido: $got)" >&2; exit 1
    fi
  else
    echo "==> (sin .sha256 publicado; se omite la verificación de hash)"
  fi
fi
head -c2 "$ZIP" | grep -q 'PK' || { echo "!! Lo descargado NO es un ZIP. Primeros bytes:" >&2; head -c300 "$ZIP" >&2; echo >&2; exit 1; }
echo "==> Extrayendo"
unzip -q "$ZIP" -d "$STAGE"
CORE="$STAGE/moodle"
[ -f "$CORE/public/version.php" ] || { echo "!! El zip no trae moodle/public/version.php (estructura inesperada)" >&2; exit 1; }
NEWVER="$(grep -oP "release\s*=\s*'\K[^']+" "$CORE/public/version.php" | head -1)"
echo "==> Núcleo nuevo: $NEWVER  (estructura split)"

# 3 · Guardar los plugins propios ----------------------------------------
echo "==> Copiando aparte los plugins propios"
for p in "${KEEP_PLUGINS[@]}"; do
  if [ "$SRC_BASE" = "." ]; then src="$REPO/$p"; else src="$REPO/$SRC_BASE/$p"; fi
  if [ -d "$src" ]; then
    mkdir -p "$STAGE/keep/$(dirname "$p")"
    cp -a "$src" "$STAGE/keep/$p"
    echo "    + $p"
  else
    echo "    ? $p  (no existe; se omite)"
  fi
done

# 4 · Borrar el árbol viejo (salvo KEEP_FILES) --------------------------
echo "==> Limpiando árbol antiguo"
declare -A KEEP=(); for f in "${KEEP_FILES[@]}"; do KEEP["$f"]=1; done
shopt -s dotglob nullglob
for item in "$REPO"/*; do
  base="$(basename "$item")"
  [ -n "${KEEP[$base]:-}" ] && continue
  rm -rf "$item"
done
shopt -u dotglob nullglob

# 5 · Instalar el núcleo nuevo (raíz + public/) -----------------------
echo "==> Instalando núcleo $NEWVER"
rsync -a \
  --exclude='/.git' --exclude='/.gitignore' --exclude='/.env' --exclude='/.env.example' \
  --exclude='/config.php' --exclude='/config-local.php' \
  --exclude='/README.md' --exclude='/INSTALL.md' --exclude='/upgrade-core.sh' \
  "$CORE"/ "$REPO"/

# 6 · Recolocar los plugins propios bajo public/ --------------------
echo "==> Restaurando plugins propios en public/"
for p in "${KEEP_PLUGINS[@]}"; do
  [ -d "$STAGE/keep/$p" ] || continue
  rm -rf "${REPO:?}/public/$p"
  mkdir -p "$REPO/public/$(dirname "$p")"
  cp -a "$STAGE/keep/$p" "$REPO/public/$p"
  echo "    + public/$p"
done

# 7 · Reinstalar fuentes propias de TCPDF (p.ej. la "arial" de los diplomas) -
# public/lib se pisó entero en el paso 5; fonts/ (en KEEP_FILES) sobrevivió
# y se copia aquí encima de las fuentes de fábrica de TCPDF.
if [ -d "$REPO/fonts/tcpdf/compiled" ] && [ -n "$(ls -A "$REPO/fonts/tcpdf/compiled" 2>/dev/null)" ]; then
  echo "==> Reinstalando fuentes TCPDF propias (fonts/tcpdf/compiled/)"
  mkdir -p "$REPO/public/lib/tcpdf/fonts"
  cp -a "$REPO/fonts/tcpdf/compiled/." "$REPO/public/lib/tcpdf/fonts/"
  echo "    + $(ls "$REPO/fonts/tcpdf/compiled" | wc -l) ficheros"
fi

cat <<EOF

===================================================================
 Núcleo actualizado a:  $NEWVER   (estructura split: la app va en public/)
 Rama:                   $BRANCH
 Respaldo:               $([ "$DID_BACKUP" -eq 1 ] && echo "$BACKDIR" || echo "(omitido, ver arriba por qué)")

 SIGUIENTES PASOS
 1) git status                       # el diff es enorme (reorg + core)
 2) php composer.phar install --no-dev --optimize-autoloader
    (vendor/ es de RUNTIME en 5.x -router, htmlpurifier, phpmailer...-,
     no solo dev; el viejo se borró con el resto del árbol)
 3) Actualizar los plugins de terceros a su versión para $NEWVER
    (bajar de https://moodle.org/plugins y reemplazar la carpeta en public/):
      theme_moove   mod_customcert   mod_hvp   auth_userkey
      format_remuiformat   qformat_h5p   theme_trema
 4) Servir desde public/ :
      local:  php -S localhost:8080 -t public
      QA:     symlink del dominio -> <repo>/public
 5) Probar sobre una COPIA de la BD:
      mysqldump -u root educaxpert_aula > backup-4.5.sql
      mysql -u root -e "CREATE DATABASE educaxpert_aula_m5 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
      mysql -u root educaxpert_aula_m5 < backup-4.5.sql
      sed -i 's/^MOODLE_DB_NAME=.*/MOODLE_DB_NAME=educaxpert_aula_m5/' .env
 6) php admin/cli/checks.php
 7) php admin/cli/upgrade.php --non-interactive
 8) php admin/cli/purge_caches.php
 9) Si upgrade.php se detiene por un plugin -> actualízalo/desactívalo y repite 7
10) git add -A && git commit -m "Migración a $NEWVER (estructura public/)"
===================================================================
EOF
