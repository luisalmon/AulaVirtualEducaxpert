<?php  // Moodle configuration file — Aula Virtual EducaXpert
//
// La configuración se toma de variables de entorno (o de un archivo .env en
// esta misma carpeta). Copia .env.example a .env y ajusta los valores.
// Este archivo NO contiene secretos y puede versionarse.

unset($CFG);
global $CFG;
$CFG = new stdClass();

// -------------------------------------------------------------------
//  Carga de .env (opcional, sin dependencias externas)
// -------------------------------------------------------------------
(function () {
    $envfile = __DIR__ . '/.env';
    if (!is_readable($envfile)) {
        return;
    }
    foreach (file($envfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        if (strncmp($line, 'export ', 7) === 0) {
            $line = substr($line, 7);
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")
                && substr($value, -1) === $value[0]) {
            // Valor entre comillas: se respeta tal cual (sin quitar comentarios).
            $value = substr($value, 1, -1);
        } else {
            // Valor sin comillas: se descarta un comentario en linea (  # ...).
            if (($hash = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $hash));
            }
        }
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
})();

if (!function_exists('aula_env')) {
    /**
     * Lee una variable de entorno con valor por defecto y casteo básico.
     */
    function aula_env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        switch (strtolower($value)) {
            case 'true':  return true;
            case 'false': return false;
            case 'null':  return null;
        }
        return $value;
    }
}

// -------------------------------------------------------------------
//  Base de datos
// -------------------------------------------------------------------
$CFG->dbtype    = aula_env('MOODLE_DB_TYPE', 'mariadb');
$CFG->dblibrary = 'native';
$CFG->dbhost    = aula_env('MOODLE_DB_HOST', '127.0.0.1');
$CFG->dbname    = aula_env('MOODLE_DB_NAME', 'educaxpert_aula');
$CFG->dbuser    = aula_env('MOODLE_DB_USER', 'root');
$CFG->dbpass    = (string) aula_env('MOODLE_DB_PASS', '');
$CFG->prefix    = aula_env('MOODLE_DB_PREFIX', 'mdl_');
$CFG->dboptions = array(
    'dbpersist'   => (bool) aula_env('MOODLE_DB_PERSIST', false),
    'dbport'      => aula_env('MOODLE_DB_PORT', '3306'),
    'dbsocket'    => aula_env('MOODLE_DB_SOCKET', ''),
    'dbcollation' => aula_env('MOODLE_DB_COLLATION', 'utf8mb4_general_ci'),
);

// -------------------------------------------------------------------
//  Rutas y URL
// -------------------------------------------------------------------
$CFG->wwwroot  = aula_env('MOODLE_WWWROOT', 'http://localhost:8080');
$CFG->dataroot = aula_env('MOODLE_DATAROOT', dirname(__DIR__) . '/moodledata');
$CFG->admin    = aula_env('MOODLE_ADMIN_DIR', 'admin');
$CFG->directorypermissions = octdec(aula_env('MOODLE_DIR_PERMISSIONS', '0777'));

// -------------------------------------------------------------------
//  Entorno / depuración
// -------------------------------------------------------------------
$CFG->slasharguments = (int) aula_env('MOODLE_SLASH_ARGUMENTS', '0');

if ((int) aula_env('MOODLE_PASSWORD_POLICY', '0') === 0) {
    $CFG->passwordpolicy = false;
}

if ((int) aula_env('MOODLE_DEBUG', '1') === 1) {
    $CFG->debug = (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    $CFG->debugdisplay = (int) aula_env('MOODLE_DEBUG_DISPLAY', '1');
}

// -------------------------------------------------------------------
//  Integración SENCE (Chile)
// -------------------------------------------------------------------
$CFG->sence = array(
    'urliniciosence'  => aula_env('SENCE_URL_INICIO',   'https://sistemas.sence.cl/rce/Registro/IniciarSesion'),
    'urlcierresence'  => aula_env('SENCE_URL_CIERRE',   'https://sistemas.sence.cl/rce/Registro/CierreSesion'),
    'urliniciosencet' => aula_env('SENCE_URL_INICIO_T', 'https://sistemas.sence.cl/rce/Registro/IniciarSesion'),
    'urlcierresencet' => aula_env('SENCE_URL_CIERRE_T', 'https://sistemas.sence.cl/rce/Registro/CierreSesion'),
    'rutotec'         => aula_env('SENCE_RUT_OTEC', ''),
    'token'           => aula_env('SENCE_TOKEN', ''),
    'urlexito'        => '/blocks/sence/block_sence_exito.php',
    'urlfracaso'      => '/blocks/sence/block_sence_fracaso.php',
);

// Ajustes propios de este entorno que no encajan en una variable suelta
// (proxy inverso, sslproxy, Redis/Memcached, cachedir/tempdir alternativos,
// noemailever, etc.). No se versiona: ver .gitignore.
if (is_readable(__DIR__ . '/config-local.php')) {
    require(__DIR__ . '/config-local.php');
}

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
