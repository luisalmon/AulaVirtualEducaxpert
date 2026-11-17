#!/usr/bin/env bash
#
# instalar-fuentes-pdf.sh — Deja las fuentes PDF de los diplomas (customcert /
# TCPDF) en $MOODLE_DATAROOT/fonts/ SIN tocar el núcleo de Moodle.
#
# Moodle (public/lib/pdflib.php) usa PDF_CUSTOM_FONT_PATH = dataroot/fonts/ en
# vez de public/lib/tcpdf/fonts/ cuando esa carpeta existe y trae las fuentes
# estándar. Por eso se copian ahí las fuentes de fábrica del núcleo actual y,
# encima, las propias del repo (fonts/tcpdf/compiled/, p.ej. "arial").
#
# Correrlo en cada entorno después de actualizar el núcleo (las fuentes de
# fábrica pueden cambiar entre versiones). Es idempotente.
#
#   ./instalar-fuentes-pdf.sh                 # lee MOODLE_DATAROOT del .env
#   ./instalar-fuentes-pdf.sh /ruta/moodledata
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATAROOT="${1:-}"
if [ -z "$DATAROOT" ]; then
  DATAROOT="$(grep -E '^MOODLE_DATAROOT=' "$REPO/.env" 2>/dev/null | tail -1 | cut -d= -f2-)"
  DATAROOT="${DATAROOT%% #*}"; DATAROOT="${DATAROOT%\"}"; DATAROOT="${DATAROOT#\"}"
  DATAROOT="${DATAROOT%\'}"; DATAROOT="${DATAROOT#\'}"
fi
[ -n "$DATAROOT" ] && [ -d "$DATAROOT" ] || { echo "!! No encuentro el dataroot ('$DATAROOT'). Pásalo como argumento." >&2; exit 1; }

CORE_FONTS="$REPO/public/lib/tcpdf/fonts"
OWN_FONTS="$REPO/fonts/tcpdf/compiled"
DEST="$DATAROOT/fonts"
[ -d "$CORE_FONTS" ] || { echo "!! No existe $CORE_FONTS" >&2; exit 1; }

mkdir -p "$DEST"
cp -a "$CORE_FONTS/." "$DEST/"
n=0
if [ -d "$OWN_FONTS" ]; then
  cp -a "$OWN_FONTS/." "$DEST/"
  n="$(ls "$OWN_FONTS" | wc -l)"
fi
# Mismos permisos que el resto de moodledata (2770/660).
find "$DEST" -type d -exec chmod 2770 {} + 2>/dev/null || true
find "$DEST" -type f -exec chmod 660 {} + 2>/dev/null || true
echo "==> Fuentes en $DEST: $(ls "$DEST" | wc -l) archivos (de fábrica + $n propios)"
