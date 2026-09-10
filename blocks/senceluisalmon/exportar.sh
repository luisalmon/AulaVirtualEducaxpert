#!/bin/bash
> salida.txt
find "$(pwd)" -type f \( -name "*.php" -o -name "*.xml" \) | while read archivo; do
    echo "ruta: $archivo" >> salida.txt
    awk '{printf "%d: %s\n", NR, $0}' "$archivo" >> salida.txt
    echo "" >> salida.txt
done
