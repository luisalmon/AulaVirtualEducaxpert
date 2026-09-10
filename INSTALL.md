# Despliegue — Aula Virtual EducaXpert

Guía de instalación y despliegue de este repositorio (Moodle 4.5.3+ personalizado).
Para la descripción del proyecto y la lista de personalizaciones, ver [README.md](README.md).

> Moodle trae su propio `INSTALL.txt` genérico. **Este** documento es el
> procedimiento real de EducaXpert.

---

## Entornos

| Entorno | URL | Ubicación del código | Servidor |
|---------|-----|----------------------|----------|
| Local (desarrollo) | `http://localhost:8080` | `~/Escritorio/Proyectos/Aula/aulavirtual` | `php -S` |
| **QA** | `https://qaaula.educaxpert.cl` | `/home/educaxpert/proyect/qa/aulavirtual` | cPanel / LiteSpeed |
| Producción (referencia) | `https://aula.educaxpert.cl` | `~/aula.educaxpert.cl/aulavirtual` | cPanel / LiteSpeed |

---

## Arquitectura de configuración

```
config.php          (versionado, SIN secretos)
   ├── lee .env               → variables MOODLE_* / SENCE_*        (NO versionado)
   └── lee config-local.php   → ajustes $CFG->* propios del entorno (NO versionado)
```

`config.php` no contiene ningún valor propio: todo sale del entorno. `.env` y
`config-local.php` están en `.gitignore`; la plantilla es
[`.env.example`](.env.example). Los secretos reales (contraseñas de BD, token
SENCE) viven **solo** en el `.env` de cada servidor.

---

## 1 · Entorno local (PC de desarrollo)

Requisitos: PHP 8.2–8.4 (`mysqli`, `gd`, `curl`, `intl`, `mbstring`, `zip`,
`soap`, `xml`), MariaDB/MySQL, Composer, Git.

```bash
# 1. Código y datos (separados)
cd ~/Escritorio/Proyectos/Aula
unzip -q aulavirtual.zip          # -> ./aulavirtual/   (o: git clone <repo> aulavirtual)
unzip -q moodledata.zip           # -> ./moodledata/    (hermano, fuera del docroot)

# 2. Base de datos
mysql -u root -e "CREATE DATABASE educaxpert_aula CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u root educaxpert_aula < educaxpert_aula.sql

# 3. Configuración
cd aulavirtual
cp .env.example .env
#   MOODLE_WWWROOT=http://localhost:8080
#   MOODLE_DATAROOT=/home/<user>/Escritorio/Proyectos/Aula/moodledata
#   MOODLE_DB_* con las credenciales locales
#   MOODLE_DEBUG=1  MOODLE_PASSWORD_POLICY=0  MOODLE_SLASH_ARGUMENTS=0

# 4. Reescribir la URL del sitio en el contenido restaurado
php admin/tool/replace/cli/replace.php --search='https://aula.educaxpert.cl'        --replace='http://localhost:8080' --shorten --non-interactive
php admin/tool/replace/cli/replace.php --search='http://www.educaxpert.cl/aulavirtual' --replace='http://localhost:8080' --shorten --non-interactive
php admin/cli/purge_caches.php

# 5. Servir
../start.sh          # PHP_CLI_SERVER_WORKERS=8 php -S localhost:8080 -t aulavirtual
../stop.sh
```

Usuario administrador de pruebas local: `admintestlocal` / `123` (creado con un
script que arranca Moodle y usa `user_create_user` + `siteadmins`; requiere
`MOODLE_PASSWORD_POLICY=0`).

---

## 2 · Despliegue en QA (`qaaula.educaxpert.cl`)

Servidor cPanel con **jailshell**. El dominio `qaaula.educaxpert.cl` se sirve a
través de un **symlink**: `/home/educaxpert/qaaula.educaxpert.cl` → carpeta del código.

### 2.1 · Preparar

```bash
# guardar el config.php anterior por si hay que consultar credenciales/dataroot
cp /home/educaxpert/proyect/qa/moodle/moodle/config.php ~/qa-config.php.old 2>/dev/null || true
# respaldo del código anterior (opcional)
tar czf ~/qa-moodle-viejo-$(date +%F).tgz -C /home/educaxpert/proyect/qa moodle 2>/dev/null || true
# borrar el avance anterior (código; se conserva moodledata aparte)
rm -rf /home/educaxpert/proyect/qa/moodle/moodle /home/educaxpert/proyect/qa/moodle/*.zip
```

### 2.2 · Clonar el repositorio

```bash
cd ~
git clone https://github.com/luisalmon/AulaVirtualEducaxpert.git /home/educaxpert/proyect/qa/aulavirtual
cd /home/educaxpert/proyect/qa/aulavirtual
git log --oneline -1
```

### 2.3 · `.env` (valores reales, NO se versiona)

```bash
cat > .env <<'EOF'
MOODLE_WWWROOT=https://qaaula.educaxpert.cl
MOODLE_DATAROOT=/home/educaxpert/proyect/qa/moodledata
MOODLE_ADMIN_DIR=admin

MOODLE_DB_TYPE=mysqli
MOODLE_DB_HOST=localhost
MOODLE_DB_PORT=3306
MOODLE_DB_NAME=educaxpert_moodle_qa
MOODLE_DB_USER=educaxpert_moodle_qa
MOODLE_DB_PASS=<clave-de-la-BD-de-QA>
MOODLE_DB_PREFIX=mdl_
MOODLE_DB_COLLATION=utf8mb4_general_ci

MOODLE_DEBUG=0
MOODLE_DEBUG_DISPLAY=0
MOODLE_PASSWORD_POLICY=1
MOODLE_SLASH_ARGUMENTS=1

SENCE_RUT_OTEC=78007901-0
SENCE_TOKEN=<token-SENCE>
EOF
chmod 600 .env
```

### 2.4 · `config-local.php` (ajustes solo de QA, NO se versiona)

```bash
cat > config-local.php <<'EOF'
<?php
$CFG->noemailever = true;         // QA nunca envía correo a usuarios reales
$CFG->showcrondebugging = false;
// $CFG->sslproxy = true;         // si el login redirige a http o da "inseguro"
EOF
```

> `$CFG->dirroot` **no** se define: con la estructura plana 4.5 Moodle lo detecta solo.

### 2.5 · Base de datos de QA

La BD `educaxpert_moodle_qa` es propia de QA (no toca producción). Se vacía y se
recarga desde producción para que QA sea un espejo.

```bash
QP='<clave-de-la-BD-de-QA>'

# a) tirar todas las tablas existentes
mysql -u educaxpert_moodle_qa -p"$QP" -Nse \
 "SELECT CONCAT('DROP TABLE IF EXISTS \`',table_name,'\`;') FROM information_schema.tables WHERE table_schema='educaxpert_moodle_qa'" > /tmp/drop_qa.sql
{ echo 'SET FOREIGN_KEY_CHECKS=0;'; cat /tmp/drop_qa.sql; } | mysql -u educaxpert_moodle_qa -p"$QP" educaxpert_moodle_qa

# b) copiar prod -> qa   (credenciales de prod en ~/aula.educaxpert.cl/aulavirtual/config.php)
mysqldump -u <PROD_USER> -p'<PROD_PASS>' <PROD_DB> --single-transaction --no-tablespaces --routines --triggers \
 | mysql -u educaxpert_moodle_qa -p"$QP" educaxpert_moodle_qa

mysql -u educaxpert_moodle_qa -p"$QP" -Nse \
 "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='educaxpert_moodle_qa'"   # ~517
```

*(Alternativa sitio vacío: `php admin/cli/install_database.php --agree-license --adminpass=… --adminemail=…` y se salta 2.5b y el `replace.php` de 2.7.)*

### 2.6 · moodledata de QA

Copia de la carpeta viva de producción (consistente con el volcado de 2.5):

```bash
df -h /home/educaxpert            # comprobar espacio (~4-5 GB)
mkdir -p /home/educaxpert/proyect/qa/moodledata
cp -a /home/educaxpert/moodledata/. /home/educaxpert/proyect/qa/moodledata/

# limpiar cachés/sesiones heredadas (se regeneran)
cd /home/educaxpert/proyect/qa/moodledata
rm -rf cache/* localcache/* muc/* sessions/* temp/* trashdir/*
```

### 2.7 · Enlazar y reescribir URL

```bash
cd /home/educaxpert/proyect/qa/aulavirtual
php admin/cli/cfg.php --name=release          # checkpoint -> "4.5.3+ (Build: 20250328)"
php admin/cli/maintenance.php --enable
php admin/tool/replace/cli/replace.php --search='https://aula.educaxpert.cl' --replace='https://qaaula.educaxpert.cl' --shorten --non-interactive
php admin/tool/replace/cli/replace.php --search='http://aula.educaxpert.cl'  --replace='https://qaaula.educaxpert.cl' --shorten --non-interactive
php admin/cli/purge_caches.php
php admin/cli/maintenance.php --disable
```

`--shorten` es obligatorio: `qaaula…` es más largo que `aula…` y sin ese flag
la herramienta se niega (para URLs en `text`/`longtext` no hay truncamiento real).

### 2.8 · PHP handler + apuntar el dominio

```bash
# límites y versión de PHP: se reutilizan los de producción (están en .gitignore)
cp -n ~/aula.educaxpert.cl/aulavirtual/.htaccess  /home/educaxpert/proyect/qa/aulavirtual/ 2>/dev/null
cp -n ~/aula.educaxpert.cl/aulavirtual/.user.ini  /home/educaxpert/proyect/qa/aulavirtual/ 2>/dev/null
#   (o cPanel → MultiPHP Manager → qaaula.educaxpert.cl = PHP 8.2)

# repuntar el symlink del dominio (el sitio está caído hasta aquí)
ln -sfn /home/educaxpert/proyect/qa/aulavirtual /home/educaxpert/qaaula.educaxpert.cl
readlink -f /home/educaxpert/qaaula.educaxpert.cl
```

### 2.9 · Verificar

```bash
curl -sS -o /dev/null -w "home  %{http_code}\n" https://qaaula.educaxpert.cl/
curl -sS -o /dev/null -w "login %{http_code}\n" https://qaaula.educaxpert.cl/login/index.php
```

`200` en ambas = QA operativo. El usuario administrador se hereda del volcado de
producción.

### 2.10 · Limpieza

```bash
rm -rf /home/educaxpert/proyect/qa/moodle      # resto del avance anterior
rm -f  ~/moodledata.zip ~/qa-moodle-viejo-*.tgz   # si el disco va justo
```

---

## 3 · Actualizaciones posteriores de QA

```bash
cd /home/educaxpert/proyect/qa/aulavirtual
php admin/cli/maintenance.php --enable
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader   # solo si se añadieron dependencias
php admin/cli/upgrade.php --non-interactive        # si el código trae versión nueva
php admin/cli/purge_caches.php
php admin/cli/maintenance.php --disable
```

`.env` y `config-local.php` sobreviven al `git pull` (no versionados).

---

## 4 · Notas y problemas conocidos

- **`replace.php` → "El reemplazo es más largo que el original"**: añadir `--shorten`.
- **jailshell**: shell restringida; usar rutas absolutas, `git`/`php`/`mysql` disponibles.
- **Docroot por symlink**: `/home/educaxpert/qaaula.educaxpert.cl` es un enlace a la
  carpeta del código. Al cambiar de estructura (p. ej. 5.x con `public/` ⇄ 4.5 plano)
  hay que repuntarlo con `ln -sfn`.
- **QA no envía correo**: `$CFG->noemailever = true` en `config-local.php`.
- **Secretos**: nunca en el repo. Contraseñas de BD y token SENCE solo en `.env`.
  Si una credencial se expone (p. ej. en un commit), **rotarla** y reescribir la
  historia del repo.
- **Downgrade de Moodle**: no está permitido. La versión del código debe ser ≥ la
  de la BD. Por eso QA se recreó desde cero al pasar de 5.0.2 a 4.5.3.
- **cron**: encolar `php /home/educaxpert/proyect/qa/aulavirtual/admin/cli/cron.php`
  cada minuto (cPanel → Cron Jobs).
