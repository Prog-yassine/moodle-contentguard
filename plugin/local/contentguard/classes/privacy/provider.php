<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is distributed under the GNU General Public License, version 3 or later.

namespace local_contentguard\privacy;

defined('MOODLE_INTERNAL') || die();

/** The plugin adds no stored user records and does not send data to an external service. */
class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
