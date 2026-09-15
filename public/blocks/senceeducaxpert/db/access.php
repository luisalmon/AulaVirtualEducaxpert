<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'block/senceeducaxpert:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'manager' => CAP_ALLOW, // Solo gestores.
        ),
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ),
    'block/senceeducaxpert:myaddinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        ),
    ),
);
