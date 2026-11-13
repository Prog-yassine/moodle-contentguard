<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is distributed under the GNU General Public License, version 3 or later.

defined('MOODLE_INTERNAL') || die();

/** Upgrade early prototype installations to an installation-specific HMAC key. */
function xmldb_local_contentguard_upgrade(int $oldversion): bool {
    if ($oldversion < 2026111301) {
        if (!get_config('local_contentguard', 'watermarksalt')) {
            set_config('watermarksalt', bin2hex(random_bytes(32)), 'local_contentguard');
        }
        upgrade_plugin_savepoint(true, 2026111301, 'local', 'contentguard');
    }
    return true;
}
