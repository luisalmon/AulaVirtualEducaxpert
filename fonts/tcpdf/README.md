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

Se registra en TCPDF **con dos nombres** para cubrir los certificados ya
creados:

- **`arial`** (+ `arialb`, `ariali`, `arialbi`) — nombre "limpio", el que
  generaría cualquier fuente nueva.
- **`arial_`** (+ `arial_b`, `arial_i`, `arial_bi`) — con guion bajo al
  final. Es el nombre real que quedó grabado en los certificados
  existentes del servidor viejo: quien la registró ahí usó un archivo
  llamado `Arial_Regular.ttf` (con guion bajo antes de "Regular"), y el
  conversor de TCPDF arrastra ese guion al nombre de familia. Se descubrió
  al generar un certificado real y toparse con
  `TCPDF ERROR: Could not include font definition file: arial_`.

Cualquier certificado ya creado que referencie "arial" o "arial_" sigue
funcionando sin tocar nada en la base de datos.

## Estructura

```
fonts/tcpdf/
├── src/        Los .ttf originales (Arimo), por duplicado con y sin
│               guion bajo (Arial-Regular.ttf y Arial_Regular.ttf, etc.)
│               para que el conversor genere ambos nombres de familia.
├── compiled/   Ya convertidos al formato de TCPDF (.php + .z + .ctg.z)
├── convert.sh  Regenera compiled/ a partir de src/
└── README.md   Este archivo
```

`upgrade-core.sh` copia automáticamente `compiled/` dentro de
`public/lib/tcpdf/fonts/` después de cada actualización de núcleo (el
`public/lib` que trae el zip de Moodle se pisa entero, así que sin esto la
fuente se perdería en cada versión nueva).

## Si consiguen la Arial real con licencia

Sustituye los 8 `.ttf` de `src/` por los tuyos (mismo archivo copiado con
y sin guion bajo), **manteniendo los mismos nombres**:
`Arial-Regular.ttf` / `Arial_Regular.ttf`, `Arial-Bold.ttf` / `Arial_Bold.ttf`,
`Arial-Italic.ttf` / `Arial_Italic.ttf`, `Arial-BoldItalic.ttf` / `Arial_BoldItalic.ttf`,
y corre:

```bash
./fonts/tcpdf/convert.sh
cp fonts/tcpdf/compiled/* ../../public/lib/tcpdf/fonts/   # o desde la raíz del repo:
# cp fonts/tcpdf/compiled/* public/lib/tcpdf/fonts/
```

Los nombres de fuente siguen siendo `arial`/`arial_`, así que no hay que
tocar ningún certificado existente.
