#!/usr/bin/env bash
#
# fonts/tcpdf/convert.sh — Convierte los .ttf de src/ al formato interno de
# TCPDF y los deja en compiled/. install.sh los copia desde ahí a
# public/lib/tcpdf/fonts/ (upgrade-core.sh lo hace solo tras cada actualización
# de núcleo).
#
# Uso:
#   ./fonts/tcpdf/convert.sh
#
# Para reemplazar la fuente por la Arial real (con licencia propia, no se
# puede versionar aquí por copyright): sobrescribe los 4 .ttf de src/ con los
# tuyos MANTENIENDO los mismos nombres de fichero (Arial-Regular.ttf,
# Arial-Bold.ttf, Arial-Italic.ttf, Arial-BoldItalic.ttf) y vuelve a correr
# este script. El nombre de fuente que ve Moodle/TCPDF ("arial") no cambia,
# así que los certificados existentes que ya la usan siguen funcionando.
#
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"

command -v php >/dev/null 2>&1 || { echo "!! Falta 'php' en el PATH" >&2; exit 1; }
[ -f "$REPO/config.php" ] || { echo "!! No se encuentra $REPO/config.php" >&2; exit 1; }

mkdir -p "$HERE/compiled"

cat > /tmp/tcpdf-convert-$$.php <<EOF
<?php
define('CLI_SCRIPT', true);
require('$REPO/config.php');
require_once(\$CFG->libdir . '/pdflib.php');
\$src = \$argv[1];
\$out = \$argv[2];
\$name = \TCPDF_FONTS::addTTFfont(\$src, '', '', 96, \$out);
echo (\$name ? "OK -> \$name\n" : "FALLO con \$src\n");
EOF

for f in "$HERE"/src/*.ttf; do
  [ -f "$f" ] || continue
  echo "==> Convirtiendo $(basename "$f")"
  php /tmp/tcpdf-convert-$$.php "$f" "$HERE/compiled/"
done
rm -f /tmp/tcpdf-convert-$$.php

echo
echo "Listo. Para usarla ya mismo (sin esperar al próximo upgrade-core.sh):"
echo "  cp $HERE/compiled/* $REPO/public/lib/tcpdf/fonts/"
