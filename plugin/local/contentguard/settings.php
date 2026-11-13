<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is distributed under the GNU General Public License, version 3 or later.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_contentguard', get_string('pluginname', 'local_contentguard'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading('local_contentguard/introduction',
        get_string('settingsheading', 'local_contentguard'), get_string('settingsdescription', 'local_contentguard')));
    $settings->add(new admin_setting_configcheckbox('local_contentguard/enabled',
        get_string('enabled', 'local_contentguard'), get_string('enabled_desc', 'local_contentguard'), 0));

    $courses = [];
    if ($ADMIN->fulltree) {
        foreach (get_courses('all', 'fullname ASC', 'c.id,c.fullname') as $course) {
            if ((int)$course->id !== SITEID) {
                $courses[$course->id] = format_string($course->fullname);
            }
        }
    }
    $settings->add(new admin_setting_configmultiselect('local_contentguard/courseids',
        get_string('courseids', 'local_contentguard'), get_string('courseids_desc', 'local_contentguard'), [], $courses));
    foreach (['restrictcopy', 'restrictcontextmenu', 'restrictshortcuts', 'watermark'] as $setting) {
        $settings->add(new admin_setting_configcheckbox('local_contentguard/' . $setting,
            get_string($setting, 'local_contentguard'), get_string($setting . '_desc', 'local_contentguard'), 1));
    }
    $settings->add(new admin_setting_configcheckbox('local_contentguard/aiguard',
        get_string('aiguard', 'local_contentguard'), get_string('aiguard_desc', 'local_contentguard'), 0));
    $settings->add(new admin_setting_configselect('local_contentguard/opacity',
        get_string('opacity', 'local_contentguard'), get_string('opacity_desc', 'local_contentguard'), '0.13', [
            '0.08' => get_string('opacitylight', 'local_contentguard'),
            '0.13' => get_string('opacitynormal', 'local_contentguard'),
            '0.20' => get_string('opacitystrong', 'local_contentguard'),
        ]));
}
