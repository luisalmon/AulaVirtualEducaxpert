# Aula Virtual EducaXpert

Plataforma de formación en línea de **EducaXpert**, construida sobre
[Moodle](https://moodle.org) **4.5.3+ (LTS, build 20250328)** y personalizada
para la operación de EducaXpert (cursos, diplomas y reportería SENCE).

> **Propiedad de EducaXpert.** Este repositorio y sus personalizaciones son
> propiedad de EducaXpert. El núcleo de Moodle se distribuye bajo licencia
> [GNU GPL v3+](https://www.gnu.org/licenses/gpl-3.0.html); las adaptaciones,
> temas y bloques propios de este repositorio quedan sujetos a esa misma
> licencia por derivación, con la titularidad de EducaXpert.

---

## Contenido del repositorio

Se versiona **todo el árbol de código** de Moodle (núcleo + personalizaciones).
**No** se versionan las dependencias ni la configuración local:

| Ruta | Versionado | Nota |
|------|:---:|------|
| Núcleo Moodle (`lib/`, `mod/`, `admin/`, …) | ✅ | Moodle 4.5.3+ |
| `theme/moove`, `theme/trema` | ✅ | Temas personalizados (activo: **moove**) |
| `blocks/sence`, `blocks/senceluisalmon` | ✅ | Integración SENCE (Chile) |
| `config.php` | ✅ | Sin secretos: lee todo desde variables de entorno |
| `.env.example` | ✅ | Plantilla de configuración |
| `.env` | ❌ | Configuración real de cada entorno (secretos) |
| `vendor/`, `node_modules/` | ❌ | Se instalan con Composer / npm |
| `moodledata/` | ❌ | Vive **fuera** de este árbol |
| `error_log`, `*.log` | ❌ | Ruido de ejecución |

---

## Requisitos

- **PHP 8.2 – 8.4** con extensiones: `mysqli` (o `pgsql`), `gd`, `curl`,
  `intl`, `mbstring`, `xml`/`soap`, `zip`, `iconv`, `opcache`
- **MariaDB 10.6+** / **MySQL 8.0+** (o PostgreSQL 13+)
- **Composer 2.x** (dependencias PHP de desarrollo)
- **Node.js 20.x + npm** (solo si se va a compilar JS/CSS de los temas)
- Un directorio **`moodledata`** con permisos de escritura, fuera del docroot

---

## Puesta en marcha (entorno local)

```bash
# 1. Clonar
git clone <url-del-repo> aulavirtual
cd aulavirtual

# 2. Configuración
cp .env.example .env
$EDITOR .env          # ajustar BD, MOODLE_WWWROOT y MOODLE_DATAROOT (ruta ABSOLUTA)

# 3. Dependencias (opcional: solo para tests / build de temas)
composer install
npm install            # + npx grunt   si se editan los temas

# 4. Base de datos
mysql -u root -e "CREATE DATABASE educaxpert_aula CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u root educaxpert_aula < /ruta/al/backup.sql        # restaurar copia existente
#   — o, para una instalación limpia:
# php admin/cli/install_database.php --agree-license --adminpass=... --adminemail=...

# 5. Al restaurar una copia de otro dominio, reescribir la URL del sitio
php admin/tool/replace/cli/replace.php \
  --search='https://aula.educaxpert.cl' --replace="$(grep ^MOODLE_WWWROOT .env | cut -d= -f2)" \
  --non-interactive

# 6. Vaciar cachés y comprobar versión
php admin/cli/purge_caches.php
php admin/cli/upgrade.php --non-interactive     # si el código es más nuevo que la BD

# 7. Servir
php -S localhost:8080 -t .        # desarrollo rápido (PHP_CLI_SERVER_WORKERS=8 para concurrencia)
#   — o configurar un vhost Apache/Nginx apuntando a esta carpeta
```

Con `php -S` deja `MOODLE_SLASH_ARGUMENTS=0` en el `.env`; con Apache/Nginx
ponlo a `1`.

---

## Variables de entorno

`config.php` no contiene valores propios: los toma del entorno o de un archivo
`.env` en la raíz (cargado por un lector mínimo sin dependencias). Claves en
[`.env.example`](.env.example):

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
- **`blocks/sence` + `blocks/senceluisalmon`** — registro de asistencia y
  reportería hacia el sistema **SENCE** (RCE). Configurados vía `$CFG->sence`
  en `config.php`, alimentado desde `SENCE_*`.
- Presets de administración *Starter* y *Completo* incluidos en la copia.

---

## Mantenimiento

```bash
php admin/cli/cron.php            # tareas programadas (encolar en crontab cada minuto)
php admin/cli/purge_caches.php    # tras cambios de código o de tema
php admin/cli/maintenance.php --enable    # modo mantenimiento
```

## Flujo de trabajo

- Rama principal: `main`. Trabajar en ramas `feature/…` o `fix/…` y abrir PR.
- No commitear `config.php` con valores propios ni ningún `.env`.
- Al añadir plugins de terceros, documentarlos en esta sección y fijar su versión.
