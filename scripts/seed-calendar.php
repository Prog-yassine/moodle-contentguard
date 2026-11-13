<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Add a course-calendar link without changing the quiz's availability.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/calendar/lib.php');
\core\session\manager::set_user(get_admin());
$course = $DB->get_record('course', ['shortname' => 'TEST-NORMAL'], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['course' => $course->id, 'name' => 'Test de connaissances'], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
$id = (int)get_config('local_contentguard', 'quizcalendareventid');
$existing = $id ? $DB->get_record('event', ['id' => $id, 'courseid' => $course->id, 'eventtype' => 'course']) : false;
$start = $existing ? (int)$existing->timestart : (new DateTimeImmutable('today', new DateTimeZone('UTC')))->getTimestamp();
$quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);
$properties = (object)[
    'name' => 'Test de connaissances',
    'description' => '<p>Quiz Moodle : quatre questions, note sur 10, tentatives illimitées.</p>'
        . '<p><a href="' . $quizurl->out() . '">Ouvrir le test</a></p>',
    'format' => FORMAT_HTML,
    'courseid' => $course->id,
    'categoryid' => 0,
    'groupid' => 0,
    'userid' => get_admin()->id,
    'modulename' => '',
    'instance' => 0,
    'eventtype' => 'course',
    'timestart' => $start,
    'timeduration' => DAYSECS,
    'visible' => 1,
];
if ($existing) {
    $event = calendar_event::load($existing->id);
    $event->update($properties);
} else {
    $event = calendar_event::create($properties);
    if (!$event) {
        throw new moodle_exception('Failed to create calendar event.');
    }
    set_config('quizcalendareventid', $event->id, 'local_contentguard');
}
echo json_encode([
    'eventid' => (int)$event->id,
    'date' => gmdate('Y-m-d', $start),
    'allday' => true,
    'courseid' => (int)$course->id,
    'quizurl' => $quizurl->out(false),
    'calendarurl' => (new moodle_url('/calendar/view.php', [
        'view' => 'day', 'course' => $course->id, 'time' => $start,
    ]))->out(false),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
