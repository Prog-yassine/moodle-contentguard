<?php
// Development configuration. The laboratory is intentionally bound to localhost.
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'db';
$CFG->dbname = 'moodle';
$CFG->dbuser = 'moodle';
$CFG->dbpass = getenv('MOODLE_DB_PASSWORD');
$CFG->prefix = 'mdl_';
$CFG->dboptions = ['dbpersist' => false, 'dbport' => 5432, 'dbsocket' => ''];
$CFG->wwwroot = rtrim(getenv('MOODLE_WWWROOT') ?: 'http://localhost:8080', '/');
// The temporary public demo terminates TLS at its HTTPS tunnel endpoint.
$CFG->sslproxy = str_starts_with($CFG->wwwroot, 'https://');
$CFG->cookiesecure = $CFG->sslproxy;
$CFG->reverseproxy = getenv('MOODLE_REVERSE_PROXY') === '1';
$CFG->dataroot = '/var/moodledata';
$CFG->admin = 'admin';
$CFG->directorypermissions = 02775;
$CFG->noemailever = true;
$CFG->debug = 0;
$CFG->debugdisplay = false;
require_once(__DIR__ . '/lib/setup.php');
