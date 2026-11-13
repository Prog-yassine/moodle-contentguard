<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is distributed under the GNU General Public License, version 3 or later.

defined('MOODLE_INTERNAL') || die();

/** Generate a private installation-specific HMAC key; this is not user data. */
function xmldb_local_contentguard_install(): void {
    set_config('watermarksalt', bin2hex(random_bytes(32)), 'local_contentguard');
}
