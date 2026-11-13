<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is distributed under the GNU General Public License, version 3 or later.

defined('MOODLE_INTERNAL') || die();

/** Return the validated configuration, including defaults before the first settings save. */
function local_contentguard_configuration(): array {
    $stored = get_config('local_contentguard');
    $configuration = [];
    foreach (['enabled', 'restrictcopy', 'restrictcontextmenu', 'restrictshortcuts', 'watermark'] as $key) {
        $configuration[$key] = isset($stored->$key) ? (bool)(int)$stored->$key : $key !== 'enabled';
    }
    $configuration['aiguard'] = !empty($stored->aiguard);
    $configuration['courseids'] = array_values(array_filter(array_map('intval',
        explode(',', (string)($stored->courseids ?? ''))), static function($id) {
            return $id > SITEID;
        }));
    $opacity = (float)($stored->opacity ?? 0.13);
    $configuration['opacity'] = max(0.05, min(0.25, $opacity));
    return $configuration;
}

/** Initialise protection on supported course reading pages and the explicit demonstration. */
function local_contentguard_before_standard_html_head(): string {
    global $PAGE, $COURSE, $USER;
    static $initialised = false;

    if ($initialised || !isloggedin() || isguestuser() || empty($COURSE->id) || $COURSE->id <= SITEID) {
        return '';
    }
    $path = $PAGE->url->get_path();
    $demopath = (new moodle_url('/local/contentguard/demo.php'))->get_path();
    $isdemo = $path === $demopath;
    $allowedpaths = [
        '/course/view.php', '/mod/page/view.php', '/mod/book/view.php', '/mod/lesson/view.php',
        '/mod/quiz/view.php', '/mod/quiz/attempt.php', '/mod/quiz/review.php',
    ];
    $allowedpaths = array_map(static function($path) {
        return (new moodle_url($path))->get_path();
    }, $allowedpaths);
    if (!$isdemo && (!in_array($path, $allowedpaths, true) || $PAGE->user_is_editing())) {
        return '';
    }
    $configuration = local_contentguard_configuration();
    if (!$configuration['enabled']) {
        return '';
    }
    $context = context_course::instance($COURSE->id);
    if (!$isdemo && (is_siteadmin() || has_capability('local/contentguard:bypass', $context))) {
        return '';
    }
    if (!empty($configuration['courseids']) && !in_array((int)$COURSE->id, $configuration['courseids'], true)) {
        return '';
    }

    // This fallback also supports prototypes installed before the install/upgrade key was introduced.
    $watermarksalt = get_config('local_contentguard', 'watermarksalt');
    if (!$watermarksalt) {
        $watermarksalt = bin2hex(random_bytes(32));
        set_config('watermarksalt', $watermarksalt, 'local_contentguard');
    }
    // The pseudonym is derived in memory. No tracking records or personal data are added to the database.
    $pseudonym = strtoupper(substr(hash_hmac('sha256',
        'contentguard:' . $USER->id . ':' . $COURSE->id, $watermarksalt), 0, 10));
    $date = gmdate('Y-m-d') . ' UTC';
    $options = [
        'selector' => $isdemo ? '#local-contentguard-demo' : '#region-main',
        'restrictcopy' => $configuration['restrictcopy'],
        'restrictcontextmenu' => $configuration['restrictcontextmenu'],
        'restrictshortcuts' => $configuration['restrictshortcuts'],
        'watermark' => $configuration['watermark'],
        'opacity' => $configuration['opacity'],
        'watermarktext' => 'CG-' . $pseudonym,
        'watermarkdate' => $date,
        'aiassessment' => $configuration['aiguard'] && $path === (new moodle_url('/mod/quiz/attempt.php'))->get_path(),
        'ailabel' => get_string('ailabel', 'local_contentguard'),
        'aiinstruction' => get_string('aiinstruction', 'local_contentguard'),
        'aitile' => get_string('aitile', 'local_contentguard'),
        'aitileenglish' => 'AI: study guidance only; no direct answers',
        'blockedmessage' => get_string('blockedmessage', 'local_contentguard'),
    ];
    $PAGE->requires->js('/local/contentguard/guard.js');
    $PAGE->requires->js_init_call('M.local_contentguard.init', [$options]);
    $initialised = true;
    return '';
}
