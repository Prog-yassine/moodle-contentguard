<?php
// Copyright 2026 ContentGuard contributors.
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Create a normal Moodle quiz using the course, question and quiz APIs.
 *
 * No account password is read or changed. Existing attempts and question versions
 * are retained on subsequent runs. Supports the Moodle 4.x and 5.1 web roots.
 */
define('CLI_SCRIPT', true);
$configfile = is_file('/var/www/html/public/config.php')
    ? '/var/www/html/public/config.php' : '/var/www/html/config.php';
require($configfile);
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$admin = get_admin();
if (!$admin) {
    cli_error('Moodle must be installed before creating the quiz.');
}
\core\session\manager::set_user($admin);
$student = $DB->get_record('user', [
    'username' => 'etudiant',
    'mnethostid' => $CFG->mnet_localhost_id,
    'deleted' => 0,
], '*', MUST_EXIST);

$category = $DB->get_record('course_categories', ['idnumber' => 'contentguard-demo']);
if (!$category) {
    $category = \core_course_category::create((object) [
        'name' => 'Cours de démonstration',
        'idnumber' => 'contentguard-demo',
        'description' => '',
        'descriptionformat' => FORMAT_HTML,
        'visible' => 1,
    ]);
}

$course = $DB->get_record('course', ['shortname' => 'TEST-NORMAL']);
if (!$course) {
    $course = create_course((object) [
        'category' => $category->id,
        'shortname' => 'TEST-NORMAL',
        'fullname' => 'Test de connaissances — informatique',
        'idnumber' => 'contentguard-normal-quiz-course',
        'summary' => '<p>Un test de quatre questions sur les bases de l’informatique.</p>',
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => 1,
        'visible' => 1,
        'startdate' => 0,
        'enddate' => 0,
        'lang' => 'fr',
        'enablecompletion' => 0,
        'showgrades' => 1,
        'maxbytes' => 10485760,
    ]);
}

$manual = enrol_get_plugin('manual');
if (!$manual) {
    cli_error('The manual enrolment plugin is required.');
}
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
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
}
$manual->enrol_user($instance, $student->id, $studentrole->id, 0, 0, ENROL_USER_ACTIVE);

$quiz = $DB->get_record('quiz', ['course' => $course->id, 'name' => 'Test de connaissances']);
if (!$quiz) {
    $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
    $quizform = (object) [
        'modulename' => 'quiz',
        'module' => $module->id,
        'name' => 'Test de connaissances',
        'section' => 1,
        'visible' => 1,
        'showdescription' => 1,
        'intro' => '<p>Répondez aux quatre questions, puis cliquez sur « Tout envoyer et terminer ». '
            . 'Vous pourrez consulter votre note et la correction après l’envoi.</p>',
        'introformat' => FORMAT_HTML,
        'timeopen' => 0,
        'timeclose' => 0,
        'timelimit' => 0,
        'overduehandling' => 'autosubmit',
        'graceperiod' => 0,
        'preferredbehaviour' => 'deferredfeedback',
        'canredoquestions' => 0,
        'attempts' => 0,
        'attemptonlast' => 0,
        'grademethod' => QUIZ_GRADEHIGHEST,
        'decimalpoints' => 2,
        'questiondecimalpoints' => -1,
        'questionsperpage' => 0,
        'navmethod' => QUIZ_NAVMETHOD_FREE,
        'shuffleanswers' => 0,
        'sumgrades' => 0,
        'grade' => 10,
        'quizpassword' => '',
        'subnet' => '',
        'browsersecurity' => '',
        'delay1' => 0,
        'delay2' => 0,
        'showuserpicture' => 0,
        'showblocks' => 0,
        'completion' => COMPLETION_DISABLED,
        'completionattemptsexhausted' => 0,
        'completionminattempts' => 0,
        'allowofflineattempts' => 0,
    ];
    // During the attempt, keep answers private; show the normal review afterwards.
    foreach (['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback',
            'generalfeedback', 'rightanswer', 'overallfeedback'] as $option) {
        $quizform->{$option . 'during'} = $option === 'attempt' ? 1 : 0;
        foreach (['immediately', 'open', 'closed'] as $when) {
            $quizform->{$option . $when} = 1;
        }
    }
    $created = add_moduleinfo($quizform, $course);
    $quiz = $DB->get_record('quiz', ['id' => $created->instance], '*', MUST_EXIST);
    $cmid = $created->coursemodule;
} else {
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
    $cmid = $cm->id;
}
$quiz->cmid = $cmid;
$questioncontext = context_module::instance($cmid);

// Moodle 5.0+ keeps a quiz's private questions in its module context. The older
// public API below creates the equivalent module category on Moodle 4.x.
if ((new ReflectionFunction('question_get_default_category'))->getNumberOfParameters() >= 2) {
    $questioncategory = question_get_default_category($questioncontext->id, true);
} else {
    $questioncategory = question_make_default_categories([$questioncontext]);
}
if (!$questioncategory) {
    cli_error('Could not create the question category for the quiz.');
}

/** Create editor values accepted by Moodle's question forms, with no uploaded files. */
function contentguard_quiz_editor(string $text): array {
    return ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => 0];
}

$specifications = [
    [
        'key' => 'normal-html', 'type' => 'multichoice', 'name' => 'Structure d’une page web',
        'text' => '<p>Quel langage sert principalement à structurer le contenu d’une page web ?</p>',
        'answers' => ['HTML', 'CSS', 'SQL', 'Python'],
        'feedback' => 'HTML décrit la structure et le contenu d’une page web.',
    ],
    [
        'key' => 'normal-stockage', 'type' => 'multichoice', 'name' => 'Stockage des données',
        'text' => '<p>Quel équipement permet de conserver des fichiers lorsque l’ordinateur est éteint ?</p>',
        'answers' => ['Un disque SSD', 'Un clavier', 'Un écran', 'Une souris'],
        'feedback' => 'Un disque SSD est un support de stockage non volatil.',
    ],
    [
        'key' => 'normal-ip', 'type' => 'truefalse', 'name' => 'Adresse IP',
        'text' => '<p>Une adresse IP sert à identifier une interface réseau pour communiquer sur un réseau.</p>',
        'feedback' => 'Une adresse IP permet l’adressage des interfaces sur un réseau IP.',
    ],
    [
        'key' => 'normal-https', 'type' => 'shortanswer', 'name' => 'Connexion web sécurisée',
        'text' => '<p>Quel préfixe, composé de cinq lettres, indique une connexion web chiffrée '
            . 'au début d’une adresse ? Écrivez uniquement les cinq lettres.</p>',
        'feedback' => 'Le préfixe HTTPS indique l’utilisation d’une connexion web chiffrée.',
    ],
];

$questionids = [];
foreach ($specifications as $specification) {
    // Read the latest existing version of this seed-owned question. Creation and
    // version links are handled by save_question(), never by hand-written SQL.
    $existing = $DB->get_records_sql(
        'SELECT q.id FROM {question} q
           JOIN {question_versions} qv ON qv.questionid = q.id
           JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
          WHERE qbe.questioncategoryid = :categoryid AND qbe.idnumber = :idnumber
          ORDER BY qv.version DESC',
        ['categoryid' => $questioncategory->id, 'idnumber' => $specification['key']], 0, 1
    );
    if ($existing) {
        $questionid = (int) reset($existing)->id;
    } else {
        $form = (object) [
            'category' => $questioncategory->id . ',' . $questioncontext->id,
            'name' => $specification['name'],
            'idnumber' => $specification['key'],
            'questiontext' => contentguard_quiz_editor($specification['text']),
            'generalfeedback' => contentguard_quiz_editor($specification['feedback']),
            'defaultmark' => 1,
            'penalty' => 0,
            'status' => 'ready',
            'hint' => [],
        ];
        if ($specification['type'] === 'multichoice') {
            $form->single = 1;
            $form->shuffleanswers = 0;
            $form->answernumbering = 'abc';
            $form->showstandardinstruction = 1;
            $form->shownumcorrect = 0;
            $form->answer = array_map('contentguard_quiz_editor', $specification['answers']);
            $form->fraction = [1, 0, 0, 0];
            $form->feedback = array_map('contentguard_quiz_editor', [
                'Bonne réponse.', 'Réponse incorrecte.', 'Réponse incorrecte.', 'Réponse incorrecte.',
            ]);
            $form->correctfeedback = contentguard_quiz_editor('Bonne réponse.');
            $form->partiallycorrectfeedback = contentguard_quiz_editor('Réponse partiellement correcte.');
            $form->incorrectfeedback = contentguard_quiz_editor('Réponse incorrecte.');
        } else if ($specification['type'] === 'truefalse') {
            $form->correctanswer = 1;
            $form->penalty = 1;
            $form->showstandardinstruction = 1;
            $form->feedbacktrue = contentguard_quiz_editor('Bonne réponse.');
            $form->feedbackfalse = contentguard_quiz_editor('La proposition est vraie.');
        } else {
            $form->usecase = 0;
            $form->answer = ['HTTPS', '*'];
            $form->fraction = [1, 0];
            $form->feedback = [contentguard_quiz_editor('Bonne réponse.'),
                contentguard_quiz_editor('La réponse attendue est HTTPS.')];
        }
        $question = question_bank::get_qtype($specification['type'])->save_question(
            (object) ['qtype' => $specification['type']], $form
        );
        $questionid = (int) $question->id;
    }
    quiz_add_quiz_question($questionid, $quiz);
    $questionids[$specification['key']] = $questionid;
}

if (class_exists('\mod_quiz\quiz_settings')) {
    \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
} else {
    quiz_update_sumgrades($quiz);
}
$quiz = $DB->get_record('quiz', ['id' => $quiz->id], '*', MUST_EXIST);
quiz_grade_item_update($quiz);

$section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
course_update_section($course, $section, [
    'name' => 'Évaluation',
    'summary' => '<p>Ouvrez le test ci-dessous pour commencer.</p>',
    'summaryformat' => FORMAT_HTML,
]);

// An empty scope already means all courses. Preserve that meaning and all other
// protection settings; otherwise add this new course to the existing selection.
$currentscope = trim((string) get_config('local_contentguard', 'courseids'));
if ($currentscope !== '') {
    $courseids = array_filter(array_map('intval', explode(',', $currentscope)));
    $courseids[] = (int) $course->id;
    set_config('courseids', implode(',', array_unique($courseids)), 'local_contentguard');
}
rebuild_course_cache($course->id, true);

echo json_encode([
    'courseid' => (int) $course->id,
    'quizid' => (int) $quiz->id,
    'quizcmid' => (int) $cmid,
    'studentid' => (int) $student->id,
    'questions' => $questionids,
    'questioncount' => count($questionids),
    'grade' => (float) $quiz->grade,
    'sumgrades' => (float) $quiz->sumgrades,
    'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    'quizurl' => (new moodle_url('/mod/quiz/view.php', ['id' => $cmid]))->out(false),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
