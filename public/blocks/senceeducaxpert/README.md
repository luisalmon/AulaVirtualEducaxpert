# SenceEducaxpert

Bloque de Moodle que integra el control de asistencia de **SENCE** (Servicio
Nacional de Capacitación y Empleo, Chile) en los cursos de EducaXpert:
obliga a los alumnos a registrar su ingreso/cierre de sesión en el portal
oficial de SENCE antes de poder ver el contenido del curso, y da a
profesores/gestores un panel para configurar fechas por grupo, forzar el
código SENCE, liberar asistencias marcadas por error y consultar el
historial técnico de transmisiones.

**Desarrollado por Luis Almon para EducaXpert** (`lalmonacid@educaxpert.cl`).

## Historia

Este plugin reemplaza a un `block_sence` anterior (2020) que se conservaba
solo como referencia. `senceeducaxpert` es la integración SENCE real en uso,
y en septiembre de 2026 se revisó a fondo para dejarla lista para
**Moodle 5.2**: se renombró desde `block_senceluisalmon`, se cerraron varios
huecos de seguridad y se completó lo que faltaba para un plugin "serio"
(privacidad, escapado de salida, índices de BD). Ver el registro de cambios
en el historial de git del repositorio.

## Componentes

- `block_senceeducaxpert.php` — el bloque: vista de gestor (panel de grupos)
  y vista de alumno (candado de asistencia).
- `exito.php` / `error.php` — URLs de retoma que SENCE llama por POST al
  volver de su portal.
- `process_dj.php` — registra el clic en "Generar Declaración Jurada".
- `reset_group.php` / `reset_user.php` / `save_group_config.php` — acciones
  de gestor, llamadas por `fetch()` desde el propio bloque.
- `report.php` — historial de transmisiones (solo administradores del
  sitio, `moodle/site:config`).
- `classes/privacy/provider.php` — implementación de la API de privacidad
  de Moodle sobre `block_senceeducaxpert_log` (exportar/borrar datos de un
  usuario, GDPR/Ley 21.719).

## Seguridad — qué se revisó y corrigió (2026-09)

- **CSRF**: `reset_group.php`, `reset_user.php`, `save_group_config.php` y
  `process_dj.php` no validaban `sesskey()` — cualquier página externa podía
  disparar esas acciones a nombre de un profesor/gestor con sesión abierta.
  Corregido: las 4 exigen `require_sesskey()`, y el bloque manda la sesskey
  en cada llamada `fetch()`.
- **`error.php` sin `require_login()`**: cualquiera, sin sesión, podía
  insertar registros en el log y disparar el correo de alerta a soporte.
  Corregido: ahora exige sesión (`require_login($courseid)`).
- **XSS**: nombres de alumnos y otros campos se interpolaban en el HTML sin
  `s()`. Corregido en todas las vistas.
- **`process_dj.php` — límite conocido, no resuelto del todo**: aunque ya
  exige `sesskey()` (evita que OTRO sitio lo dispare por el alumno), un
  alumno puede seguir visitando la URL a mano y auto-marcarse "éxito" sin
  pasar realmente por la Declaración Jurada de SENCE. SENCE no entrega nada
  firmado que permita verificar que la DJ se generó de verdad, así que
  cerrar esto del todo necesitaría un mecanismo aparte (token de un solo
  uso emitido justo antes de abrir el enlace externo, con expiración
  corta). Queda anotado para una futura revisión.
- **`exito.php`**: SENCE no firma su callback, así que la validación sigue
  siendo "¿fue un POST no vacío?" — no se puede verificar criptográficamente
  que la respuesta vino realmente de SENCE con los datos que mandamos, es
  una limitación del protocolo de SENCE, no del plugin.

## Config por instancia

El editor del bloque (`edit_form.php`) permite elegir la **Línea de
Capacitación** (`config_linea`) por instancia — antes se mostraba en el
formulario pero el código ignoraba el valor y mandaba siempre `3` a SENCE;
ahora `get_login_form()` sí lo usa.
