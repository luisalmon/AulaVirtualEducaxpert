# Aula Virtual EducaXpert

Plataforma de formación en línea de **EducaXpert**, construida sobre
[Moodle](https://moodle.org) **5.2.2+** y personalizada para la operación de
EducaXpert (cursos, diplomas y reportería SENCE).

> **Propiedad de EducaXpert.** Este repositorio y sus personalizaciones son
> propiedad de EducaXpert. El núcleo de Moodle se distribuye bajo licencia
> [GNU GPL v3+](https://www.gnu.org/licenses/gpl-3.0.html); las adaptaciones,
> temas y bloques propios quedan sujetos a esa misma licencia por derivación,
> con la titularidad de EducaXpert.

> **Estado:** migrado de 4.5.3 a 5.2.2+ (rama `upgrade/moodle-502`). Falta subir
> los plugins de terceros a su release 5.2 y configurar el router web — ver
> [Actualizar Moodle](#actualizar-moodle) e [`INSTALL.md`](INSTALL.md).

---

## Estructura del repositorio (Moodle 5.x — directorio `public/`)

Desde Moodle 5.1 el paquete usa la estructura **split**: la aplicación web vive
en `public/` y solo eso es el *document root*; el resto queda fuera del webroot.

```
<repo>/
├── admin/cli/          Solo scripts CLI (upgrade, cron, purge_caches…)
├── lib/                Shim que reenvía a public/lib
├── config.php          Config env-based de EducaXpert  (raíz, fuera del webroot)
├── config-dist.php     Plantilla de Moodle
├── scripts/  Gruntfile.js  composer.json  package.json  …   Herramientas de build
└── public/             *** DOCUMENT ROOT ***  (la app: admin, mod, theme, lib…)
    ├── config.php      Stub:  require '../config.php'
    ├── version.php     5.2.2+
    ├── theme/moove   theme/trema
    ├── blocks/sence  blocks/senceluisalmon
    ├── mod/customcert  mod/hvp
    ├── auth/userkey   course/format/remuiformat   question/format/h5p
    └── …
```

| Ruta | Versionado | Nota |
|------|:---:|------|
| Núcleo Moodle (raíz + `public/`) | ✅ | Moodle 5.2.2+ |
| `public/theme/moove`, `public/theme/trema` | ✅ | Temas propios (activo: **moove**) |
| `public/blocks/sence`, `public/blocks/senceluisalmon` | ✅ | Integración SENCE (Chile) |
| `config.php` | ✅ | Sin secretos: lee variables de entorno / `.env` |
| `.env.example` | ✅ | Plantilla de configuración |
| `.env`, `config-local.php` | ❌ | Config real de cada entorno (secretos) |
| `vendor/`, `node_modules/` | ❌ | Se instalan con Composer / npm (en la raíz) |
| `moodledata/` | ❌ | Vive **fuera** de este árbol |
| `**/.github/workflows/`, `error_log`, `*.log`, `*.swp` | ❌ | CI ajeno / ruido |

El *document root* del servidor web (Apache/LiteSpeed/Nginx o `php -S`) debe
apuntar a **`<repo>/public`**. Los scripts CLI se ejecutan **desde la raíz**
(`php admin/cli/…`).

---

## Requisitos (Moodle 5.2)

- **PHP 8.2 – 8.4** (8.3 recomendado) con: `mysqli`/`pgsql`, `gd`, `curl`,
  `intl`, `mbstring`, `xml`/`soap`, `zip`, `iconv`, `sodium`, `opcache`
- **MariaDB 10.6.7+** / **MySQL 8.0+** (o PostgreSQL 13+)
- **Composer 2.x**, y **Node 20+ / npm** solo si se compila JS/CSS de temas
- Un **`moodledata`** con escritura, fuera del *document root*
- Para el **router** (`core_router`, nuevo en 5.x): un rewrite en el servidor web
  que envíe las rutas a `public/r.php` — ver [`INSTALL.md`](INSTALL.md). Sin él el
  sitio funciona vía `/r.php/…` pero el check de estado sale en rojo.

---

## Puesta en marcha (entorno local)

```bash
git clone <url-del-repo> aulavirtual
cd aulavirtual

cp .env.example .env
$EDITOR .env      # BD, MOODLE_WWWROOT, MOODLE_DATAROOT (ruta ABSOLUTA)

composer install --no-dev            # opcional (solo tests / herramientas)

# Base de datos
mysql -u root -e "CREATE DATABASE educaxpert_aula CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u root educaxpert_aula < /ruta/al/backup.sql
php admin/cli/upgrade.php --non-interactive     # si el código es más nuevo que la BD

# Al restaurar una copia de otro dominio, reescribir la URL (nota: el tool va bajo public/)
php public/admin/tool/replace/cli/replace.php \
  --search='https://aula.educaxpert.cl' \
  --replace="$(grep ^MOODLE_WWWROOT .env | cut -d= -f2)" --shorten --non-interactive

php admin/cli/purge_caches.php

# Servir  (¡desde public/!)
php -S localhost:8080 -t public          # PHP_CLI_SERVER_WORKERS=8 para concurrencia
#   o:  ../start.sh    (detecta public/ automáticamente)
```

Con `php -S` deja `MOODLE_SLASH_ARGUMENTS=0` en el `.env`; con Apache/Nginx,
`1`. En local por HTTP, `cookiesecure` debe estar a `0` en la BD; en QA/prod
(HTTPS) a `1`.

---

## Variables de entorno

`config.php` no contiene valores propios: los toma del entorno o de un `.env` en
la raíz (lector mínimo sin dependencias). Además, si existe `config-local.php` en
la raíz, se incluye antes del arranque (ajustes `$CFG->*` propios del entorno:
`sslproxy`, `noemailever`, Redis, etc.). Claves en [`.env.example`](.env.example):

| Variable | Por defecto | Descripción |
|----------|-------------|-------------|
| `MOODLE_WWWROOT` | `http://localhost:8080` | URL pública, sin barra final |
| `MOODLE_DATAROOT` | `<carpeta-padre>/moodledata` | Ruta absoluta a moodledata |
| `MOODLE_ADMIN_DIR` | `admin` | Carpeta de administración |
| `MOODLE_DB_TYPE` | `mariadb` | `mariadb` / `mysqli` / `pgsql` |
| `MOODLE_DB_HOST` / `MOODLE_DB_PORT` | `127.0.0.1` / `3306` | |
| `MOODLE_DB_NAME` | `educaxpert_aula` | |
| `MOODLE_DB_USER` / `MOODLE_DB_PASS` | `root` / *(vacío)* | Credenciales de BD |
| `MOODLE_DB_PREFIX` | `mdl_` | Prefijo de tablas |
| `MOODLE_DB_COLLATION` | `utf8mb4_general_ci` | |
| `MOODLE_DEBUG` / `MOODLE_DEBUG_DISPLAY` | `1` / `1` | Modo desarrollador |
| `MOODLE_PASSWORD_POLICY` | `0` | `0` desactiva la política (solo dev) |
| `MOODLE_SLASH_ARGUMENTS` | `0` | `0` para `php -S`, `1` para Apache/Nginx |
| `SENCE_RUT_OTEC`, `SENCE_TOKEN` | *(vacío)* | Secretos de la integración SENCE |

---

## Personalizaciones de EducaXpert

- **Tema `moove`** (activo) y **tema `trema`** — identidad visual EducaXpert.
- **`public/blocks/sence` + `public/blocks/senceluisalmon`** — asistencia y
  reportería hacia **SENCE** (RCE). Configurados vía `$CFG->sence` en `config.php`,
  alimentado desde `SENCE_*`.
- **`mod/customcert`** (+ elementos) para diplomas; **`format_remuiformat`**,
  **`mod/hvp`**, **`auth/userkey`**, **`qformat_h5p`**.

---

## Actualizar Moodle

El núcleo se actualiza **con `upgrade-core.sh`** (no se puede "desde Moodle": la
plataforma solo avisa de nuevas versiones y actualiza *plugins* y la *BD*, nunca
sus propios ficheros).

### 1 · Montar el árbol nuevo en local (en una rama)

```bash
git checkout -b upgrade/moodle-<NNN>          # p.ej. 502 = rama 5.2
./upgrade-core.sh 502
#   descarga el core de packaging.moodle.org, verifica SHA256, instala
#   raíz/ + public/, y recoloca los 9 plugins propios bajo public/.
#   Detecta si el repo venía plano (4.x) o ya split (5.x).
#   MOODLE_ZIP=/ruta/moodle.zip ./upgrade-core.sh 502   usa un zip ya bajado.
```

`<NNN>` es el **código de rama** de Moodle (`405`=4.5, `500`=5.0, `501`=5.1,
`502`=5.2), no el número de versión. `moodle-latest-<NNN>.zip` es siempre la
última publicada de esa rama.

### 2 · Probar sobre una COPIA de la BD (nunca la real)

```bash
mysqldump -u root educaxpert_aula > backup-antes.sql
mysql -u root -e "CREATE DATABASE educaxpert_aula_m5 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u root educaxpert_aula_m5 < backup-antes.sql
cp -a /ruta/moodledata /ruta/moodledata-m5           # y limpiar cache/*, sessions/*, muc/*

# apuntar a las copias (variables de entorno tienen prioridad sobre .env)
export MOODLE_DB_NAME=educaxpert_aula_m5
export MOODLE_DATAROOT=/ruta/moodledata-m5

php admin/cli/checks.php
php admin/cli/upgrade.php --non-interactive          # migra la BD  4.x -> nueva
php admin/cli/purge_caches.php
php -S localhost:8080 -t public                      # revisar en el navegador
```

### 3 · Actualizar los plugins de terceros a la nueva versión

`php admin/cli/upgrade.php` **no bloquea** por plugins viejos: quedan instalados
pero pueden fallar en runtime (APIs retiradas, Atto→TinyMCE, etc.). Para cada uno,
bajar su release compatible de <https://moodle.org/plugins> y reemplazar la
carpeta bajo `public/`:

`theme_moove`, `mod_customcert` (+ elementos; ojo `subplugins.json`, MDL-83705),
`mod_hvp`, `auth_userkey`, `format_remuiformat`, `qformat_h5p`. Verificar
`theme_trema`. Probar a fondo los in-house `block_sence` y `block_senceluisalmon`.

Repetir el paso 2 hasta que el sitio funcione sin errores (login, panel, un
curso, generar un diploma, el bloque SENCE, ajustes del tema).

### 4 · Commit, merge y despliegue

```bash
git add -A && git commit -m "Actualización a Moodle X.Y (estructura public/)"
git push -u origin upgrade/moodle-<NNN>
# tras validar: PR -> merge a main -> git tag vX.Y-educaxpert
```

Despliegue en QA/producción (**no** se corre `upgrade-core.sh` allí — se hace
`git pull` del árbol ya migrado): ver [`INSTALL.md`](INSTALL.md) §3. Resumen:
backup de BD + moodledata → `maintenance --enable` → `git pull` → `php admin/cli/upgrade.php`
→ `purge_caches` → repuntar el *document root* a `.../public` → mover `.htaccess`/`.user.ini`
a `public/` → subir PHP a 8.3 → configurar el rewrite del router → `maintenance --disable`.

---

## Mantenimiento

```bash
php admin/cli/cron.php            # encolar en crontab cada minuto
php admin/cli/purge_caches.php    # tras cambios de código o de tema
php admin/cli/maintenance.php --enable / --disable
```

## Flujo de trabajo

- Rama principal `main`. Trabajo en ramas `feature/…`, `fix/…`, `upgrade/…` y PR.
- Nunca commitear `.env` ni `config-local.php` ni un `config.php` con valores propios.
- Al añadir plugins de terceros, documentarlos aquí y fijar su versión.
