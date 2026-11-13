<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is distributed under the GNU General Public License, version 3 or later.

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/contentguard:bypass' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ],
    ],
];
