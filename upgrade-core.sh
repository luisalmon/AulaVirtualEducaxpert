#!/usr/bin/env bash
#
# upgrade-core.sh — Actualiza el NÚCLEO de Moodle (estructura "public/" de 5.x)
# conservando los plugins propios de EducaXpert y la configuración.
# NO toca la base de datos: eso lo hace 'php admin/cli/upgrade.php' después.
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
  blocks/sence
  blocks/senceluisalmon
)
# Ficheros de la raíz del repo que nunca se tocan.
KEEP_FILES=(.git .gitignore .env .env.example config.php config-local.php
            README.md INSTALL.md upgrade-core.sh)

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

# 1 · Obtener el núcleo -----------------------------------------------------
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

# 2 · Guardar los plugins propios ----------------------------------------
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

# 3 · Borrar el árbol viejo (salvo KEEP_FILES) --------------------------
echo "==> Limpiando árbol antiguo"
declare -A KEEP=(); for f in "${KEEP_FILES[@]}"; do KEEP["$f"]=1; done
shopt -s dotglob nullglob
for item in "$REPO"/*; do
  base="$(basename "$item")"
  [ -n "${KEEP[$base]:-}" ] && continue
  rm -rf "$item"
done
shopt -u dotglob nullglob

# 4 · Instalar el núcleo nuevo (raíz + public/) -----------------------
echo "==> Instalando núcleo $NEWVER"
rsync -a \
  --exclude='/.git' --exclude='/.gitignore' --exclude='/.env' --exclude='/.env.example' \
  --exclude='/config.php' --exclude='/config-local.php' \
  --exclude='/README.md' --exclude='/INSTALL.md' --exclude='/upgrade-core.sh' \
  "$CORE"/ "$REPO"/

# 5 · Recolocar los plugins propios bajo public/ --------------------
echo "==> Restaurando plugins propios en public/"
for p in "${KEEP_PLUGINS[@]}"; do
  [ -d "$STAGE/keep/$p" ] || continue
  rm -rf "${REPO:?}/public/$p"
  mkdir -p "$REPO/public/$(dirname "$p")"
  cp -a "$STAGE/keep/$p" "$REPO/public/$p"
  echo "    + public/$p"
done

cat <<EOF

===================================================================
 Núcleo actualizado a:  $NEWVER   (estructura split: la app va en public/)
 Rama:                   $BRANCH

 SIGUIENTES PASOS
 1) git status                       # el diff es enorme (reorg + core)
 2) Actualizar los plugins de terceros a su versión para $NEWVER
    (bajar de https://moodle.org/plugins y reemplazar la carpeta en public/):
      theme_moove   mod_customcert   mod_hvp   auth_userkey
      format_remuiformat   qformat_h5p   theme_trema
 3) Servir desde public/ :
      local:  php -S localhost:8080 -t public
      QA:     symlink del dominio -> <repo>/public
 4) Probar sobre una COPIA de la BD:
      mysqldump -u root educaxpert_aula > backup-4.5.sql
      mysql -u root -e "CREATE DATABASE educaxpert_aula_m5 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
      mysql -u root educaxpert_aula_m5 < backup-4.5.sql
      sed -i 's/^MOODLE_DB_NAME=.*/MOODLE_DB_NAME=educaxpert_aula_m5/' .env
 5) php admin/cli/checks.php
 6) php admin/cli/upgrade.php --non-interactive
 7) php admin/cli/purge_caches.php
 8) Si upgrade.php se detiene por un plugin -> actualízalo/desactívalo y repite 6
 9) git add -A && git commit -m "Migración a $NEWVER (estructura public/)"
===================================================================
EOF
