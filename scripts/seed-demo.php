<?php
// This file is part of the ContentGuard demonstration environment.
// Copyright 2026 ContentGuard contributors.
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Create an idempotent, synthetic Moodle course and enrolled student for testing.
 *
 * Run inside the Moodle PHP container after admin/cli/install.php has completed:
 * CONTENTGUARD_STUDENT_PASSWORD=... php /opt/contentguard/seed-demo.php
 *
 * The password is deliberately read only from the process environment. This script
 * never writes it to its output, Moodle course content, or a downloadable artifact.
 */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/resourcelib.php');

$studentpassword = getenv('CONTENTGUARD_STUDENT_PASSWORD');
if ($studentpassword === false || $studentpassword === '') {
    cli_error('CONTENTGUARD_STUDENT_PASSWORD must be provided. No changes were made.');
}

$admin = get_admin();
if (!$admin) {
    cli_error('Install Moodle and create its administrator before running this script.');
}
\core\session\manager::set_user($admin);

// A temporary lab should neither send messages nor accept public registrations.
set_config('registerauth', '');
set_config('auth', 'manual');
set_config('disableuserimages', 1);
set_config('guestloginbutton', 0);
set_config('noemailever', 1);
set_config('theme', 'classic');
// Keep Classic's off-screen panels from expanding the mobile quiz viewport.
set_config('scss', '@media (max-width: 767.98px) { html, body { overflow-x: hidden; } .fixed-top { width: 100vw; } }', 'theme_classic');
set_config('lang', 'fr');
set_config('langmenu', 1);
set_config('timezone', 'UTC');
set_config('brandcolor', '#244fe6', 'theme_boost');

$category = $DB->get_record('course_categories', ['idnumber' => 'contentguard-demo']);
if (!$category) {
    $category = \core_course_category::create((object) [
        'name' => 'Laboratoire ContentGuard',
        'idnumber' => 'contentguard-demo',
        'description' => 'Environnement de démonstration contenant uniquement des données fictives.',
        'descriptionformat' => FORMAT_HTML,
        'visible' => 1,
    ]);
}

$coursedata = (object) [
    'category' => $category->id,
    'shortname' => 'GUARD-DEMO',
    'fullname' => 'Atelier — contenus protégés',
    'idnumber' => 'contentguard-demo-course',
    'summary' => '<p>Un vrai cours Moodle pour tester le filigrane personnalisé, les restrictions '
        . 'de copie et leurs limites. Toutes les données de ce laboratoire sont fictives.</p>',
    'summaryformat' => FORMAT_HTML,
    'format' => 'topics',
    'numsections' => 1,
    'visible' => 1,
    'startdate' => strtotime('2026-01-01 00:00:00 UTC'),
    'enddate' => 0,
    'lang' => 'fr',
    'enablecompletion' => 0,
    'showgrades' => 0,
    'maxbytes' => 10485760,
];
$course = $DB->get_record('course', ['shortname' => $coursedata->shortname]);
if ($course) {
    $coursedata->id = $course->id;
    update_course($coursedata);
    $course = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
} else {
    $course = create_course($coursedata);
}

$userdata = (object) [
    'username' => 'etudiant',
    'auth' => 'manual',
    'password' => $studentpassword,
    'firstname' => 'Camille',
    'lastname' => 'Démo',
    'email' => 'etudiant@example.invalid',
    'maildisplay' => 0,
    'confirmed' => 1,
    'policyagreed' => 1,
    'mnethostid' => $CFG->mnet_localhost_id,
    'lang' => 'fr',
    'timezone' => 'UTC',
    'city' => '',
    'country' => '',
    'description' => 'Compte étudiant fictif pour tester ContentGuard.',
    'descriptionformat' => FORMAT_HTML,
    'suspended' => 0,
];
$student = $DB->get_record('user', [
    'username' => $userdata->username,
    'mnethostid' => $CFG->mnet_localhost_id,
    'deleted' => 0,
]);
if ($student) {
    $userdata->id = $student->id;
    user_update_user($userdata, true, true);
    $studentid = $student->id;
} else {
    $studentid = user_create_user($userdata, true, true);
}
unset($studentpassword, $userdata->password);
set_user_preference('auth_forcepasswordchange', 0, $studentid);

$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$manual = enrol_get_plugin('manual');
if (!$manual) {
    cli_error('The built-in manual enrolment plugin must be installed.');
}
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
if (!$instance) {
    $instanceid = $manual->add_instance($course, [
        'status' => ENROL_INSTANCE_ENABLED,
        'roleid' => $studentrole->id,
        'customint1' => 0,
    ]);
    $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
} else if ((int) $instance->status !== ENROL_INSTANCE_ENABLED) {
    $manual->update_status($instance, ENROL_INSTANCE_ENABLED);
    $instance->status = ENROL_INSTANCE_ENABLED;
}
$manual->enrol_user($instance, $studentid, $studentrole->id, 0, 0, ENROL_USER_ACTIVE);

// Only this synthetic course is in scope; the rest of Moodle is unaffected.
foreach ([
    'enabled' => 1,
    'courseids' => (string) $course->id,
    'watermark' => 1,
    'restrictcopy' => 1,
    'restrictcontextmenu' => 1,
    'restrictshortcuts' => 1,
    'aiguard' => 1,
] as $setting => $value) {
    set_config($setting, $value, 'local_contentguard');
}

$demourl = new moodle_url('/local/contentguard/demo.php', ['courseid' => $course->id]);
$escapedurl = $demourl->out();
$pagecontent = <<<HTML
<div style="max-width:900px;margin:0 auto;line-height:1.75;">
  <div style="background:#eff3ff;border:1px solid #dbe3ff;border-radius:20px;padding:32px;">
    <p style="color:#244fe6;font-weight:700;letter-spacing:.08em;">CONTENTGUARD · LABORATOIRE</p>
    <h2>Partager le savoir, limiter les copies.</h2>
    <p>Cette page est une activité Moodle réelle. Le plugin ajoute un filigrane personnel
    et applique les restrictions de copie choisies par l’administrateur.</p>
    <p><a href="{$escapedurl}" class="btn btn-primary">Ouvrir le banc d’essai interactif</a></p>
  </div>
  <h3>Un contenu pédagogique pour tes essais</h3>
  <p>La lumière naturelle donne son rythme à une photographie. Avant de déclencher,
  observe la direction de la lumière, les ombres et les couleurs. Un cadrage simple
  permet de guider le regard vers le sujet principal.</p>
  <p>Pour cet exercice, imagine une bibliothèque au lever du jour : un livre ouvert,
  une fenêtre à gauche et une tasse de café. Décris la composition que tu choisirais
  et explique ce que tu souhaites transmettre au lecteur.</p>
  <h3>Ce que tu peux vérifier</h3>
  <ol>
    <li>Le filigrane affiche un identifiant pseudonyme lié à ta session utilisateur et une date.</li>
    <li>Essaie la sélection, la copie et le clic droit sur ce texte.</li>
    <li>Ouvre le banc d’essai : sa zone de réponse doit rester modifiable.</li>
    <li>Prends une capture avec ton système : le filigrane apparaît sur l’image.</li>
  </ol>
  <div style="border-left:4px solid #244fe6;padding:12px 20px;background:#f8f9fc;">
    <strong>Une protection de dissuasion, avec des limites claires.</strong>
    <p>Un NFT est un mécanisme de propriété ou de traçabilité ; il ne bloque pas les captures
    d’écran. Ce prototype ne bloque pas les captures du système, les photos externes,
    ni tous les accès aux outils de développement. Les restrictions JavaScript restent contournables.</p>
  </div>
</div>
HTML;

$pagename = 'Commencer — tester les contenus protégés';
$existingpage = $DB->get_record('page', ['course' => $course->id, 'name' => $pagename]);
if (!$existingpage) {
    $module = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
    $page = add_moduleinfo((object) [
        'modulename' => 'page',
        'module' => $module->id,
        'name' => $pagename,
        'section' => 1,
        'visible' => 1,
        'showdescription' => 1,
        'intro' => '<p>Découvre le prototype puis ouvre le banc d’essai interactif.</p>',
        'introformat' => FORMAT_HTML,
        'content' => $pagecontent,
        'contentformat' => FORMAT_HTML,
        'display' => RESOURCELIB_DISPLAY_OPEN,
        'printintro' => 0,
        'printlastmodified' => 0,
        'completion' => COMPLETION_DISABLED,
    ], $course);
    $pagecmid = $page->coursemodule;
} else {
    // This seed owns only its specifically named synthetic page, not user content.
    $DB->update_record('page', (object) [
        'id' => $existingpage->id,
        'content' => $pagecontent,
        'contentformat' => FORMAT_HTML,
        'timemodified' => time(),
    ]);
    $cm = get_coursemodule_from_instance('page', $existingpage->id, $course->id, false, MUST_EXIST);
    $pagecmid = $cm->id;
}

$section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
course_update_section($course, $section, [
    'name' => 'Ton espace de test',
    'summary' => '<p>Connecte-toi avec le compte étudiant, ouvre la ressource ci-dessous '
        . 'ou <a href="' . $escapedurl . '">accède directement au banc d’essai</a>.</p>',
    'summaryformat' => FORMAT_HTML,
]);
rebuild_course_cache($course->id, true);

echo json_encode([
    'courseid' => (int) $course->id,
    'studentid' => (int) $studentid,
    'studentusername' => 'etudiant',
    'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    'pageurl' => (new moodle_url('/mod/page/view.php', ['id' => $pagecmid]))->out(false),
    'demourl' => $demourl->out(false),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
