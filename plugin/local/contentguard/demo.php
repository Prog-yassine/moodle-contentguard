<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is distributed under the GNU General Public License, version 3 or later.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
if ($courseid <= SITEID) {
    require_login();
    throw new moodle_exception('nocourse', 'local_contentguard');
}
$course = get_course($courseid);
require_login($course, false);
if (isguestuser()) {
    throw new require_login_exception();
}
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/contentguard/demo.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('demotitle', 'local_contentguard'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');

$configuration = local_contentguard_configuration();
$active = $configuration['enabled'] && (empty($configuration['courseids']) ||
    in_array($courseid, $configuration['courseids'], true));
$lang = static function(string $key): string {
    return s(get_string($key, 'local_contentguard'));
};
$statuslabels = [
    'restrictcopy' => 'statuscopy',
    'restrictcontextmenu' => 'statuscontextmenu',
    'restrictshortcuts' => 'statusshortcuts',
    'watermark' => 'statuswatermark',
];

echo $OUTPUT->header();
?>
<div class="contentguard-lab" data-contentguard-active="<?php echo $active ? '1' : '0'; ?>">
    <p class="cg-eyebrow"><?php echo $lang('eyebrow'); ?></p>
    <div class="cg-hero">
        <div>
            <h1 class="cg-title"><?php echo $lang('herotitle'); ?></h1>
            <p class="cg-intro"><?php echo $lang('herointro'); ?></p>
        </div>
        <span class="cg-badge<?php echo $active ? '' : ' cg-off'; ?>">
            <?php echo $lang($active ? 'protectionactive' : 'protectioninactive'); ?>
        </span>
    </div>

    <div class="cg-grid">
        <div>
            <section class="cg-card" aria-labelledby="cg-reading-heading">
                <div class="cg-card-header">
                    <span id="cg-reading-heading"><?php echo $lang('readingarea'); ?></span>
                    <span class="cg-subtle"><?php echo $lang('samplecourse'); ?></span>
                </div>
                <article class="cg-reading" id="local-contentguard-demo" tabindex="0">
                    <div class="cg-art" aria-hidden="true"><span><?php echo $lang('artlabel'); ?></span></div>
                    <h2><?php echo $lang('arttitle'); ?></h2>
                    <p><?php echo $lang('articleone'); ?></p>
                    <p><?php echo $lang('articletwo'); ?></p>
                    <blockquote><?php echo $lang('articlequote'); ?></blockquote>
                </article>
            </section>
            <section class="cg-card cg-test">
                <label for="cg-answer"><?php echo $lang('testlabel'); ?></label>
                <p id="cg-answer-description"><?php echo $lang('testdescription'); ?></p>
                <textarea id="cg-answer" rows="3" aria-describedby="cg-answer-description"
                    placeholder="<?php echo $lang('testplaceholder'); ?>" autocomplete="off"></textarea>
            </section>
        </div>
        <aside class="cg-side">
            <section class="cg-card cg-panel">
                <h2><?php echo $lang('configuration'); ?></h2>
                <ul class="cg-status-list">
                    <?php foreach ($statuslabels as $key => $label) {
                        $ison = $active && $configuration[$key]; ?>
                        <li>
                            <span><?php echo $lang($label); ?></span>
                            <span class="cg-state<?php echo $ison ? '' : ' cg-off'; ?>"
                                data-contentguard-setting="<?php echo s($key); ?>">
                                <?php echo $lang($ison ? 'on' : 'off'); ?>
                            </span>
                        </li>
                    <?php } ?>
                </ul>
            </section>
            <section class="cg-card cg-panel">
                <h2><?php echo $lang('trytitle'); ?></h2>
                <?php foreach (['stepone', 'steptwo', 'stepthree'] as $index => $step) { ?>
                    <p class="cg-step"><span class="cg-step-number"><?php echo $index + 1; ?></span>
                        <span><?php echo $lang($step); ?></span></p>
                <?php } ?>
                <p class="cg-subtle"><?php echo $lang('steplimit'); ?></p>
            </section>
        </aside>
    </div>

    <div class="cg-limit">
        <strong><?php echo $lang('limittitle'); ?></strong>
        <p><?php echo $lang('limitdescription'); ?></p>
    </div>
    <div class="cg-footer">
        <span><?php echo $lang('footerprivacy'); ?></span>
        <span>
            <a href="<?php echo (new moodle_url('/course/view.php', ['id' => $courseid]))->out(); ?>">
                <?php echo $lang('courselink'); ?></a>
            <?php if (has_capability('moodle/site:config', context_system::instance())) { ?>
                &nbsp;·&nbsp;
                <a href="<?php echo (new moodle_url('/admin/settings.php', ['section' => 'local_contentguard']))->out(); ?>">
                    <?php echo $lang('settingslink'); ?></a>
            <?php } ?>
        </span>
    </div>
</div>
<?php
echo $OUTPUT->footer();
