# Parches propios sobre plugins de terceros

El núcleo de Moodle **no se modifica** (ver `instalar-fuentes-pdf.sh` y la
verificación contra el upstream oficial). Lo que sí lleva cambios propios son
algunos plugins de terceros. **Al actualizar uno de estos plugins desde
moodle.org hay que volver a aplicar su parche** (buscar `EducaXpert` en el
código para ubicarlos):

| Plugin | Archivo | Qué hace |
|---|---|---|
| theme_moove | `public/theme/moove/layout/drawers.php` | Pinta la región de bloques `content` solo en el layout `course`. Sin esto, "Vista general de curso" sale **dos veces** en Mis cursos (Moodle reutiliza `drawers.php` para otros layouts que ya pintan esa región). |
| mod_customcert | `public/mod/customcert/classes/service/pdf_generation_service.php` (`compute_filename_for_user`) | Igual que el aula 4.5: si la actividad no define un patrón propio de nombre, el diploma se descarga como **"Nombre Apellido Nombre del curso.pdf"** (de fábrica sería el nombre de la plantilla). |
| mod_customcert | `public/mod/customcert/classes/service/certificate_download_service.php` (`download_all_issues_for_instance`) | En "Descargar todos" cada PDF del ZIP lleva ese mismo nombre, sin subcarpetas (de fábrica: `nombre_apellido/certificate.pdf`). |
