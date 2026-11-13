<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is distributed under the GNU General Public License, version 3 or later.

namespace local_contentguard;

defined('MOODLE_INTERNAL') || die();

/** Register page assets through the Moodle 4.4+ hook API. */
final class hook_callbacks {
    public static function before_standard_head(\core\hook\output\before_standard_head_html_generation $hook): void {
        require_once(__DIR__ . '/../lib.php');
        $hook->add_html(local_contentguard_before_standard_html_head());
    }
}
