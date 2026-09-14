# Fuente "Arial" para los diplomas (mod_customcert / TCPDF)

Los certificados de EducaXpert usan una fuente `arial` en TCPDF. Arial es
una fuente **propietaria de Microsoft** — no se puede descargar ni
redistribuir libremente, así que aquí **no va la Arial real**.

En su lugar se usa **[Arimo](https://fonts.google.com/specimen/Arimo)**
(Google, licencia Apache 2.0, libre), diseñada específicamente como
sustituto **métricamente compatible** con Arial: mismo ancho de letra por
carácter, mismo interlineado — el texto ocupa exactamente el mismo espacio
y a simple vista es indistinguible. Es el mismo sustituto que usan
LibreOffice y Google Docs cuando no hay Arial instalada.

Se registra en TCPDF con el nombre **`arial`** (+ `arialb`, `ariali`,
`arialbi` para negrita/cursiva/negrita‑cursiva), así que cualquier
certificado ya creado que referencie la fuente "arial" sigue funcionando
sin tocar nada en la base de datos.

## Estructura

```
fonts/tcpdf/
├── src/        Los 4 .ttf originales (Arimo)
├── compiled/   Ya convertidos al formato de TCPDF (.php + .z + .ctg.z)
├── convert.sh  Regenera compiled/ a partir de src/
└── README.md   Este archivo
```

`upgrade-core.sh` copia automáticamente `compiled/` dentro de
`public/lib/tcpdf/fonts/` después de cada actualización de núcleo (el
`public/lib` que trae el zip de Moodle se pisa entero, así que sin esto la
fuente se perdería en cada versión nueva).

## Si consiguen la Arial real con licencia

Sustituye los 4 `.ttf` de `src/` por los tuyos, **manteniendo los mismos
nombres de fichero** (`Arial-Regular.ttf`, `Arial-Bold.ttf`,
`Arial-Italic.ttf`, `Arial-BoldItalic.ttf`), y corre:

```bash
./fonts/tcpdf/convert.sh
cp fonts/tcpdf/compiled/* ../../public/lib/tcpdf/fonts/   # o desde la raíz del repo:
# cp fonts/tcpdf/compiled/* public/lib/tcpdf/fonts/
```

El nombre de fuente sigue siendo `arial`, así que no hay que tocar ningún
certificado existente.
