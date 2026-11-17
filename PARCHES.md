# Parches propios sobre plugins de terceros

El núcleo de Moodle **no se modifica** (ver `instalar-fuentes-pdf.sh` y la
verificación contra el upstream oficial). Lo que sí lleva cambios propios son
algunos plugins de terceros. **Al actualizar uno de estos plugins desde
moodle.org hay que volver a aplicar su parche** (buscar `EducaXpert` en el
código para ubicarlos):

| Plugin | Archivo | Qué hace |
|---|---|---|
| theme_moove | `public/theme/moove/layout/drawers.php` | Pinta la región de bloques `content` solo en el layout `course`. Sin esto, "Vista general de curso" sale **dos veces** en Mis cursos (Moodle reutiliza `drawers.php` para otros layouts que ya pintan esa región). |
