# Actualizar el aula de producción: Moodle 4.5.3+ → 5.3.1+

Guía para pasar `aula.educaxpert.cl` de la rama `main` (Moodle 4.5.3+,
estructura plana, PHP 8.3) a la rama `upgrade/moodle-503` (Moodle 5.3.1+,
estructura `public/`, PHP 8.4).

**Ensayada el 2026-11-17 en QA** sobre una copia exacta de producción del
mismo día (código de `sites/aula`, base `educaxpert_aula` y moodledata),
con los mismos pasos. Resultado: actualización correcta, **35/35 pruebas del
bloque SENCE y 21/21 del resto del aula** (servicio web de la web, SSO
`auth_userkey`, Remui, H5P, SCORM, cuestionarios, diplomas con su fuente y su
nombre, app móvil). El núcleo de Moodle queda idéntico al oficial; lo propio
va en plugins (`PARCHES.md`) y en configuración (`.env`, `config-local.php`).

## Tiempos del ensayo

| Paso | Tiempo |
|---|---|
| 1. Mantenimiento | instantáneo |
| 2. Respaldos (BD 22 MB gz + moodledata 4,9 GB + config) | 41 s |
| 3-4. `.env`, `config-local.php` y cambio de rama | 12 s |
| 5. Raíz `public/`, PHP 8.4 | 3 s |
| 6. `upgrade.php` (4.5.3+ → 5.3.1+) | 58 s |
| 7. Bloques viejos, fuentes, 2ª pasada de upgrade, cachés | ~30 s |
| **Sitio fuera de servicio** | **~2 min 30 s** |

Sumar unos minutos para las verificaciones antes de quitar el mantenimiento.

## Antes del día del cambio

- Avisar a los alumnos (≈10 minutos de mantenimiento con margen).
- Elegir un horario sin alumnos marcando SENCE: el bloque pasa de
  `block_senceluisalmon` a `block_senceeducaxpert` y sus datos se migran solos
  (grupos, historial, ajustes, instancias; el bloque antiguo `block_sence`
  queda en "modo curso").
- ~6 GB libres en disco para los respaldos.

## Pasos (en el servidor, como `educaxpert`)

> ⚠️ **No usar `umask 077` en la sesión donde se hace el `git checkout`.** En
> el ensayo eso dejó 68 mil archivos de Moodle en 600/700 y el sitio entero
> respondió **403**. Hacerlo con el umask normal (022) o, si pasa, corregir con
> `find . -path ./.git -prune -o -type d -perm 700 -exec chmod 755 {} +` y lo
> mismo para archivos 600 → 644 (salvo `.env` y `config-local.php`).

```bash
SITE=~/sites/aula
DATA=~/data/moodledata
BK=~/backups/$(date +%F)-actualizacion-aula
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
PHP84="/opt/cpanel/ea-php84/root/usr/bin/php -d max_input_vars=5000"
cd $SITE
```

1. **Mantenimiento**: `$PHP83 admin/cli/maintenance.php --enable`
2. **Respaldos** en `$BK` (`mkdir -m 700`): `mysqldump --single-transaction
   --no-tablespaces --routines --triggers educaxpert_aula | gzip`, copia de
   moodledata con `rsync -a` (sin `cache/ localcache/ sessions/ temp/
   trashdir/`) y copia de `config.php`, `.htaccess`, `.user.ini`, `php.ini`.
   Las credenciales de MySQL van en un `--defaults-extra-file` 600 que se borra
   al final, nunca en la línea de comandos.
3. **`.env` + `config-local.php`** en `$SITE` (600, no se versionan). El
   `.env` se arma con los valores del `config.php` actual:
   `MOODLE_WWWROOT`, `MOODLE_DATAROOT`, `MOODLE_ADMIN_DIR=admin`,
   `MOODLE_DB_TYPE=mysqli`, `MOODLE_DB_HOST=localhost`, `MOODLE_DB_PORT=3306`,
   `MOODLE_DB_NAME/USER/PASS`, `MOODLE_DB_PREFIX=mdl_`,
   `MOODLE_DB_COLLATION=utf8mb4_general_ci`, `MOODLE_DIR_PERMISSIONS=02770`,
   `MOODLE_SLASH_ARGUMENTS=1`, y **obligatoriamente**:
   - `MOODLE_PASSWORD_POLICY=1` — si falta, el `config.php` del repo
     **desactiva la política de contraseñas** (su valor por defecto es 0).
   - `MOODLE_DEBUG=0` y `MOODLE_DEBUG_DISPLAY=0` — si faltan, queda en
     **modo desarrollador mostrando errores**.

   `config-local.php` en producción: `$CFG->routerconfigured = true;` (en QA
   además `noemailever` y `showcrondebugging = false`).
4. **Código**:
   ```bash
   git fetch origin
   git update-index --no-skip-worktree config.php
   git checkout -- config.php
   git checkout -b upgrade/moodle-503 origin/upgrade/moodle-503
   ```
5. **Servidor web**: mover `.htaccess`, `.user.ini` y `php.ini` de la raíz a
   `public/` cambiando `ea-php83` → `ea-php84` (rutas de sesión y handler), y
   **agregar al final de `public/.htaccess` el router de Moodle 5** (sin esto
   `admin/cli/checks.php` marca `core_router` CRÍTICO y las rutas nuevas
   `/core/...`, `/api/rest/v2/...` dan 404):
   ```apache
   # --- Router de Moodle 5.x ---
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^(.*)$ r.php [QSA,L]
   ```
   Luego raíz del subdominio y PHP (también se puede desde cPanel → Dominios
   y MultiPHP Manager):
   ```bash
   cpapi2 SubDomain changedocroot subdomain=aula rootdomain=educaxpert.cl dir=sites/aula/public
   uapi LangPHP php_set_vhost_versions version=ea-php84 vhost=aula.educaxpert.cl
   ```
   Y el cron (`crontab -e`):
   `* * * * * /opt/cpanel/ea-php84/root/usr/bin/php -d max_input_vars=5000 /home/educaxpert/sites/aula/admin/cli/cron.php >/dev/null`
6. **Base de datos**: `$PHP84 admin/cli/upgrade.php --non-interactive`
   (58 s en el ensayo). Debe decir `block_senceeducaxpert: migración desde los
   bloques SENCE anteriores: {"grupos":155,"historial":…,"instancias":28,
   "instancias_block_sence":23,…}` y `finalizada con éxito`.
7. **Después**:
   ```bash
   $PHP84 admin/cli/uninstall_plugins.php --plugins=block_senceluisalmon,block_sence --run
   # el desinstalador deja 2 instancias huérfanas (una en el contexto de
   # sistema y la del curso que tenía los dos bloques): borrarlas con
   # blocks_delete_instance() para blockname IN ('sence','senceluisalmon')
   ./instalar-fuentes-pdf.sh
   $PHP84 admin/cli/upgrade.php --is-pending || $PHP84 admin/cli/upgrade.php --non-interactive
   $PHP84 admin/cli/purge_caches.php
   ```
   (Tras desinstalar los bloques viejos queda "actualización pendiente" por el
   hash de versiones; una segunda pasada de `upgrade.php` lo resuelve.)
8. **Fin del mantenimiento**: `$PHP84 admin/cli/maintenance.php --disable`

## Verificar

- `admin/cli/checks.php`: sin errores ni críticos (en particular `core_router`
  en OK); en producción tampoco debe quedar la advertencia del cron.
- Portada, login, Mis cursos (**una** sola "Vista general de curso"), un curso
  Remui, H5P, SCORM, cuestionario, diploma (se descarga como "Nombre Apellido
  Curso.pdf"), `/admin/index.php` sin errores.
- La web: crear usuario, matricular y entrar con `auth_userkey`.
- SENCE: `block_senceeducaxpert/ambiente` debe haber quedado en **`prod`**
  (viene de `block_senceluisalmon`); revisar el bloque en un curso con grupo
  activo como alumno y como profesor.
- **Registro del sitio**: Moodle 5.3 agrega datos nuevos al registro en
  moodle.org y redirige a los administradores a "Registro del sitio" hasta que
  uno lo confirme. En producción: entrar como administrador y actualizar el
  registro una vez. (En QA **nunca** actualizarlo: QA trae copiado el registro
  de producción y lo pisaría; ahí se borra solo localmente de
  `mdl_registration_hubs` y se pone `registrationpending = 0`.)

## Volver atrás (si algo sale mal)

1. Mantenimiento on.
2. `git checkout main`, restaurar `config.php` del respaldo y
   `git update-index --skip-worktree config.php`.
3. Devolver `.htaccess`, `.user.ini`, `php.ini` del respaldo a la raíz; raíz
   del subdominio a `sites/aula` y PHP `ea-php83`; cron con `ea-php83`.
4. Restaurar la BD desde el `.sql.gz` (borrando antes todas las tablas) y
   moodledata desde la copia (`rsync -a --delete`).
5. Purgar cachés y quitar el mantenimiento.

## Lo que encontró el ensayo

- **Producción tiene hoy un error** en Administración → Notificaciones
  (`/admin/index.php`): *Unknown method … theme_moove admin_renderer ::
  moodlenet_removal_warning* (Moove 4.5.2 contra el núcleo 4.5.3+). Con la
  actualización desaparece.
- Permisos 600/700 por `umask 077` → 403 en todo el sitio (ver aviso arriba).
- Falta del router de Moodle 5 en `public/.htaccess` → `core_router` crítico.
- Redirección de administradores al registro del sitio (ver "Verificar").
- Valores por defecto peligrosos del `config.php` del repo
  (`MOODLE_PASSWORD_POLICY`, `MOODLE_DEBUG`): definirlos siempre en `.env`.
- Instancias huérfanas de los bloques SENCE viejos tras desinstalarlos, y la
  segunda pasada de `upgrade.php`.
- Producción no se tocó en ningún momento del ensayo (solo se leyó).
