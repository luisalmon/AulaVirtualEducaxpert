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

Este plugin reemplaza a un `block_sence` anterior (2020, itic.cl), que en
producción seguía puesto en 24 cursos (2 visibles en 2026-11). `senceeducaxpert` es la integración SENCE real en uso,
y en septiembre de 2026 se revisó a fondo para dejarla lista para
**Moodle 5.2**: se renombró desde `block_senceluisalmon`, se cerraron varios
huecos de seguridad y se completó lo que faltaba para un plugin "serio"
(privacidad, escapado de salida, índices de BD). Ver el registro de cambios
en el historial de git del repositorio.

## v3.0 (2026-11) — Moodle 5.3 y todas las funciones de los bloques anteriores

El aula de Moodle 4.5 tenía **dos** integraciones SENCE: `block_senceluisalmon`
(la de uso diario, por grupos) y el bloque antiguo `block_sence` (itic.cl, un
código por curso, que además dependía de un parche en `course/view.php` del
núcleo). La v3.0 reúne las funciones de ambas **sin tocar el núcleo**:

- **Migración automática** (`classes/local/migracion.php`, se ejecuta al
  instalar o actualizar el bloque): trae la configuración de grupos, el
  historial completo de conexiones, los ajustes globales y las instancias de
  `block_senceluisalmon`, y convierte las instancias de `block_sence` a este
  bloque en *modo curso* con su misma configuración. Es idempotente. Después
  se pueden desinstalar los dos bloques viejos.
- **Modo de registro por curso**: *por grupos* (como hasta ahora) o *un
  código para todo el curso* (Código SENCE + Código curso, fecha de término
  opcional), como el bloque antiguo.
- **Opciones por curso** (editar el bloque): línea de capacitación (1, 3 o 6
  FPT), ambiente test/producción (o el global), botón **"Cerrar sesión
  SENCE"** (CierreSesion con el `IdSesionSence` devuelto al iniciar), **rol**
  que debe marcar asistencia, **grupo eximido**, y correo/WhatsApp de soporte
  propios (el correo recibe las alertas de error de ese curso).
- **Resumen de configuración** para el gestor, con avisos de códigos
  demasiado cortos o credenciales OTEC faltantes, y aviso visible de *modo
  test* para gestores y alumnos.
- **Corrección del bloqueo en ciclo**: hasta v2.2 un segundo inicio de sesión
  en el mismo día se anotaba como "cierre" y el candado solo contaba los
  "inicio", así que pasadas las 2 horas el alumno no podía volver a entrar.
  Ahora el tipo de evento sale de lo que se pidió a SENCE (`a=inicio|cierre`
  en la URL de retoma).
- Códigos de error 100 y 210 agregados al diccionario; los tokens de retorno
  vencidos se limpian solos.

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
