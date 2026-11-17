<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_senceeducaxpert\local;

/**
 * Traspasa a este bloque los datos de las dos integraciones SENCE anteriores
 * del aula de Moodle 4.5:
 *
 *  - block_senceluisalmon (la que se usa a diario): configuración de grupos
 *    (fecha de término y código forzado), historial completo de conexiones,
 *    ajustes globales (RUT OTEC, token, ambiente, soporte) y las instancias
 *    del bloque en cada curso.
 *  - block_sence (itic.cl, 2020): sus instancias pasan a este bloque en
 *    "modo curso" con la misma configuración (código SENCE, línea, rol,
 *    grupo eximido, cierre de sesión, contacto y modo test).
 *
 * Es idempotente: se puede correr más de una vez sin duplicar nada. Las
 * tablas de los plugins viejos no se tocan (se borran al desinstalarlos).
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class migracion {

    /** @var string[] Ajustes globales de block_senceluisalmon que se copian. */
    const AJUSTES = ['otec_name', 'otec_rut', 'otec_token', 'support_email', 'support_whatsapp', 'ambiente'];

    /**
     * Ejecuta la migración completa.
     *
     * @return array Contadores de lo que se migró.
     */
    public static function ejecutar(): array {
        $r = [
            'grupos' => self::migrar_grupos(),
            'historial' => self::migrar_historial(),
            'ajustes' => self::migrar_ajustes(),
            'instancias' => self::migrar_instancias_senceluisalmon(),
        ];
        [$r['instancias_block_sence'], $r['block_sence_omitidas']] = self::migrar_instancias_block_sence();
        return $r;
    }

    /**
     * Copia la configuración por grupo (fecha de término y código forzado).
     *
     * @return int Grupos copiados.
     */
    protected static function migrar_grupos(): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('block_senceluisalmon_groups')) {
            return 0;
        }
        $columnas = $DB->get_columns('block_senceluisalmon_groups');
        $n = 0;
        $rs = $DB->get_recordset('block_senceluisalmon_groups', null, 'id');
        foreach ($rs as $g) {
            if ($DB->record_exists('block_senceeducaxpert_groups', ['groupid' => $g->groupid])) {
                continue;
            }
            $DB->insert_record('block_senceeducaxpert_groups', (object) [
                'groupid' => $g->groupid,
                'fecha_limite' => (int) $g->fecha_limite,
                'codigo_forzado' => isset($columnas['codigo_forzado']) ? $g->codigo_forzado : null,
            ]);
            $n++;
        }
        $rs->close();
        return $n;
    }

    /**
     * Copia el historial de conexiones SENCE (una sola vez).
     *
     * @return int Registros copiados.
     */
    protected static function migrar_historial(): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('block_senceluisalmon_log')
                || get_config('block_senceeducaxpert', 'migrado_historial')) {
            return 0;
        }
        $n = 0;
        $lote = [];
        $rs = $DB->get_recordset('block_senceluisalmon_log', null, 'id');
        foreach ($rs as $l) {
            $lote[] = (object) [
                'userid' => $l->userid,
                'courseid' => $l->courseid,
                'eventtype' => $l->eventtype,
                'status' => $l->status,
                'glosa' => $l->glosa,
                'timecreated' => $l->timecreated,
                'payload' => $l->payload,
            ];
            if (count($lote) >= 500) {
                $DB->insert_records('block_senceeducaxpert_log', $lote);
                $n += count($lote);
                $lote = [];
            }
        }
        $rs->close();
        if ($lote) {
            $DB->insert_records('block_senceeducaxpert_log', $lote);
            $n += count($lote);
        }
        set_config('migrado_historial', time(), 'block_senceeducaxpert');
        return $n;
    }

    /**
     * Copia los ajustes globales que todavía no estén definidos aquí.
     *
     * @return int Ajustes copiados.
     */
    protected static function migrar_ajustes(): int {
        $viejos = (array) get_config('block_senceluisalmon');
        $actuales = (array) get_config('block_senceeducaxpert');
        $n = 0;
        foreach (self::AJUSTES as $nombre) {
            if (!array_key_exists($nombre, $viejos) || $viejos[$nombre] === '') {
                continue;
            }
            if (isset($actuales[$nombre]) && $actuales[$nombre] !== '') {
                continue;
            }
            set_config($nombre, $viejos[$nombre], 'block_senceeducaxpert');
            $n++;
        }
        return $n;
    }

    /**
     * Pasa las instancias de block_senceluisalmon a este bloque. La
     * configuración de instancia es compatible (solo la línea de capacitación).
     *
     * @return int Instancias convertidas.
     */
    protected static function migrar_instancias_senceluisalmon(): int {
        global $DB;
        $n = 0;
        foreach ($DB->get_records('block_instances', ['blockname' => 'senceluisalmon'], 'id') as $bi) {
            if (self::contexto_ya_tiene_bloque($bi->parentcontextid)) {
                continue;
            }
            $config = self::leer_config($bi->configdata);
            $nueva = new \stdClass();
            if (!empty($config->config_linea)) {
                $nueva->linea = (string) $config->config_linea;
            } else if (!empty($config->linea)) {
                $nueva->linea = (string) $config->linea;
            }
            $DB->update_record('block_instances', (object) [
                'id' => $bi->id,
                'blockname' => 'senceeducaxpert',
                'configdata' => base64_encode(serialize($nueva)),
                'timemodified' => time(),
            ]);
            $n++;
        }
        return $n;
    }

    /**
     * Pasa las instancias del bloque antiguo block_sence a este bloque en
     * "modo curso". Si el curso ya tiene este bloque se deja la antigua
     * fuera (se borra al desinstalar block_sence).
     *
     * @return int[] [convertidas, omitidas]
     */
    protected static function migrar_instancias_block_sence(): array {
        global $DB;
        $convertidas = 0;
        $omitidas = 0;
        foreach ($DB->get_records('block_instances', ['blockname' => 'sence'], 'id') as $bi) {
            $ctx = \context::instance_by_id($bi->parentcontextid, IGNORE_MISSING);
            if (!$ctx || $ctx->contextlevel != CONTEXT_COURSE || self::contexto_ya_tiene_bloque($bi->parentcontextid)) {
                $omitidas++;
                continue;
            }
            $viejo = self::leer_config($bi->configdata);
            $nueva = (object) [
                'modo' => 'curso',
                'codsence' => trim((string) ($viejo->sence_curso ?? '')),
                'codigocurso' => '',
                'fecha_termino' => 0,
                'linea' => in_array((string) ($viejo->sence_linea ?? ''), ['1', '3', '6'], true)
                    ? (string) $viejo->sence_linea : '3',
                'cierre' => !empty($viejo->sence_cierre) ? 1 : 0,
                'rol' => (isset($viejo->sence_rol) && (int) $viejo->sence_rol > 0) ? (int) $viejo->sence_rol : 0,
                'grupo_eximido' => (isset($viejo->sence_grupo) && (int) $viejo->sence_grupo > 0) ? (int) $viejo->sence_grupo : 0,
                // "Productivo = No" era el modo test del bloque antiguo; si era
                // productivo se hereda el ambiente global.
                'ambiente' => (isset($viejo->sence_productivo) && (int) $viejo->sence_productivo === 0) ? 'test' : 'global',
                'support_email' => trim((string) ($viejo->sence_correo ?? '')),
                'support_whatsapp' => trim((string) ($viejo->sence_whatsap ?? '')),
            ];
            $DB->update_record('block_instances', (object) [
                'id' => $bi->id,
                'blockname' => 'senceeducaxpert',
                'configdata' => base64_encode(serialize($nueva)),
                'timemodified' => time(),
            ]);
            $convertidas++;
        }
        return [$convertidas, $omitidas];
    }

    /**
     * ¿El contexto ya tiene una instancia de este bloque?
     *
     * @param int $contextid
     * @return bool
     */
    protected static function contexto_ya_tiene_bloque(int $contextid): bool {
        global $DB;
        return $DB->record_exists('block_instances', ['blockname' => 'senceeducaxpert', 'parentcontextid' => $contextid]);
    }

    /**
     * Decodifica el configdata de una instancia de bloque.
     *
     * @param string|null $configdata
     * @return \stdClass
     */
    protected static function leer_config(?string $configdata): \stdClass {
        if (empty($configdata)) {
            return new \stdClass();
        }
        $config = unserialize_object(base64_decode($configdata));
        return is_object($config) ? $config : new \stdClass();
    }
}
