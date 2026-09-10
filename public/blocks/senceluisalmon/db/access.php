<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'block/senceluisalmon:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'manager' => CAP_ALLOW // Solo gestores
        ),
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),
    'block/senceluisalmon:myaddinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),
);
