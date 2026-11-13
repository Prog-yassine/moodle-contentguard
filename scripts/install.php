<?php
// Local development installer: credentials are supplied through container environment.
$args = [
    PHP_BINARY, '/var/www/html/admin/cli/install_database.php',
    '--agree-license', '--lang=en',
    '--fullname=Espace de test Moodle', '--shortname=Moodle Test',
    '--adminuser=admin', '--adminpass=' . getenv('MOODLE_ADMIN_PASSWORD'),
    '--adminemail=admin@example.invalid', '--supportemail=support@example.invalid',
];
$process = proc_open($args, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
exit(is_resource($process) ? proc_close($process) : 1);
