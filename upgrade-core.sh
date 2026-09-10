#!/usr/bin/env bash
#
# upgrade-core.sh — Reemplaza el NÚCLEO de Moodle conservando los plugins
# propios de EducaXpert y la configuración. NO toca la base de datos:
# eso lo hace 'php admin/cli/upgrade.php' en un paso posterior.
#
# Uso:
#   git checkout -b upgrade/moodle-502
#   ./upgrade-core.sh 502                         # descarga stable502 (Moodle 5.2)
#   MOODLE_ZIP=~/Descargas/moodle-latest-502.zip ./upgrade-core.sh 502   # zip ya bajado
#
set -euo pipefail

STABLE="${1:?Uso: ./upgrade-core.sh <stableNNN>   (p.ej. 502 para Moodle 5.2)}"
REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# Plugins NO-core: se guardan y se vuelven a poner encima del núcleo nuevo.
KEEP_PLUGINS=(
  theme/moove
  theme/trema
  mod/customcert
  mod/hvp
  auth/userkey
  course/format/remuiformat
  question/format/h5p
  blocks/sence
  blocks/senceluisalmon
)
# Ficheros/carpetas propios que NO se tocan al limpiar el núcleo viejo.
KEEP_FILES=(.git .gitignore .env .env.example config.php config-local.php
            README.md INSTALL.md upgrade-core.sh)

cd "$REPO"
echo "==> Repositorio: $REPO"

# 0 · Seguridad -------------------------------------------------------------
[ -z "$(git status --porcelain)" ] || { echo "!! Hay cambios sin commitear. Aborta." >&2; exit 1; }
BRANCH="$(git branch --show-current)"
case "$BRANCH" in
  upgrade/*) : ;;
  *) echo "!! Ponte primero en una rama:  git checkout -b upgrade/moodle-$STABLE" >&2; exit 1 ;;
esac
echo "==> Rama: $BRANCH"

# 1 · Obtener el núcleo ---------------------------------------------------------
if [ -n "${MOODLE_ZIP:-}" ]; then
  ZIP="$MOODLE_ZIP"
  echo "==> Zip local: $ZIP"
else
  ZIP="$STAGE/moodle-latest-$STABLE.zip"
  # packaging.moodle.org sirve el zip directo, sin la capa de mirrors de download.moodle.org
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
echo "==> Comprobando integridad del zip"
head -c2 "$ZIP" | grep -q 'PK' || { echo "!! Lo descargado NO es un ZIP. Primeros bytes:" >&2; head -c300 "$ZIP" >&2; echo >&2; exit 1; }
unzip -tq "$ZIP" >/dev/null
echo "==> Extrayendo"
unzip -q "$ZIP" -d "$STAGE"                 # -> $STAGE/moodle/
CORE="$STAGE/moodle"
[ -f "$CORE/version.php" ] || { echo "!! El zip no trae moodle/version.php" >&2; exit 1; }
NEWVER="$(grep -oP "release\s*=\s*'\K[^']+" "$CORE/version.php" | head -1)"
echo "==> Núcleo nuevo: $NEWVER"

# 2 · Guardar los plugins propios --------------------------------------------
echo "==> Copiando aparte los plugins propios"
for p in "${KEEP_PLUGINS[@]}"; do
  if [ -d "$REPO/$p" ]; then
    mkdir -p "$STAGE/keep/$(dirname "$p")"
    cp -a "$REPO/$p" "$STAGE/keep/$p"
    echo "    + $p"
  else
    echo "    ? $p  (no existe; se omite)"
  fi
done

# 3 · Borrar el núcleo viejo (salvo KEEP_FILES) -----------------------------
echo "==> Limpiando núcleo antiguo"
declare -A KEEP=()
for f in "${KEEP_FILES[@]}"; do KEEP["$f"]=1; done
shopt -s dotglob nullglob
for item in "$REPO"/*; do
  base="$(basename "$item")"
  [ -n "${KEEP[$base]:-}" ] && continue
  rm -rf "$item"
done
shopt -u dotglob nullglob

# 4 · Copiar el núcleo nuevo ------------------------------------------------
echo "==> Instalando núcleo $NEWVER"
rsync -a --exclude='.git' "$CORE"/ "$REPO"/

# 5 · Restaurar los plugins propios encima --------------------------------
echo "==> Restaurando plugins propios"
for p in "${KEEP_PLUGINS[@]}"; do
  [ -d "$STAGE/keep/$p" ] || continue
  rm -rf "${REPO:?}/$p"
  mkdir -p "$REPO/$(dirname "$p")"
  cp -a "$STAGE/keep/$p" "$REPO/$p"
  echo "    + $p"
done

cat <<EOF

===================================================================
 Núcleo actualizado a:  $NEWVER
 Rama:                   $BRANCH

 SIGUIENTES PASOS
 1) git status                       # revisar el diff
 2) Actualizar los plugins de terceros a su versión para $NEWVER
    (descargar de https://moodle.org/plugins y reemplazar la carpeta):
      theme_moove   mod_customcert   mod_hvp   auth_userkey
      format_remuiformat   qformat_h5p   theme_trema
 3) Trabajar sobre una COPIA de la BD:
      mysqldump -u root educaxpert_aula > backup-4.5.sql
      mysql -u root -e "CREATE DATABASE educaxpert_aula_m5 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
      mysql -u root educaxpert_aula_m5 < backup-4.5.sql
      sed -i 's/^MOODLE_DB_NAME=.*/MOODLE_DB_NAME=educaxpert_aula_m5/' .env
 4) php admin/cli/checks.php
 5) php admin/cli/upgrade.php --non-interactive
 6) php admin/cli/purge_caches.php   &&   ../start.sh
 7) Si upgrade.php se detiene por un plugin: actualízalo o desactívalo
    y repite desde el paso 5.
 8) git add -A && git commit -m "Actualización a $NEWVER"
===================================================================
EOF
