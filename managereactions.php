<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$forumid = optional_param('f', 0, PARAM_INT);
$discussionid = optional_param('d', 0, PARAM_INT);

if (!$forumid && !$discussionid) {
    throw new moodle_exception('Invalid parameters');
}

/**
 * @var \moodle_page $PAGE
 */
$PAGE->set_url('/local/reactforum/managereactions.php', ['f' => $forumid, 'd' => $discussionid]);

$discussion = $discussionid ? $DB->get_record('forum_discussions', ['id' => $discussionid], '*', MUST_EXIST) : null;
if ($discussion) {
    $forumid = $discussion->forum;
    $forummetadata = local_reactforum_getreactionmetadata($forumid);
    if ($forummetadata && $forummetadata->reactiontype != 'discussion') {
        throw new moodle_exception('Invalid reaction type');
    }
}

$forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);

$course = get_course($forum->course);
require_login($course);

$cm = get_coursemodule_from_instance('forum', $forum->id, $course->id);
if (!$cm) {
    throw new moodle_exception('Cannot get course module');
}

$modcontext = context_module::instance($cm->id);

if ($discussion) {
    if (!local_reactforum_caneditdiscussion($discussion, $modcontext)) {
        throw new moodle_exception('No permission');
    }
} else {
    require_capability('local/reactforum:forumconfig', $modcontext);
}

require_once(__DIR__ . '/classes/form/reactionsettings_form.php');
$form = new reactionsettings_form($forum->id, $discussion ? $discussion->id : null);
$redirecturl = $discussionid ? new moodle_url('/mod/forum/discuss.php', ['d' => $discussionid]) : new moodle_url('/mod/forum/view.php', ['f' => $forumid]);

if ($form->is_cancelled()) {
    redirect($redirecturl);
    exit;
}
if ($form->is_submitted() && $form->is_validated()) {
    if ($form->process()) {
        redirect($redirecturl);
        exit;
    }
}

$PAGE->set_cm($cm, $course, $forum);
$PAGE->set_context($modcontext);
$PAGE->set_title($forum->name . ': ' . get_string('reactionsettings', 'local_reactforum'));
$PAGE->set_heading($course->fullname);
if ($discussion) {
    $PAGE->navbar->add($discussion->name, new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->id]));
}

$PAGE->requires->css('/local/reactforum/styles.css');
$PAGE->requires->strings_for_js([
    'reactionstype_change_confirmation',
    'reactions_add',
    'reactions_changeimage',
    'reactions_selectfile',
    'reactions_cancel',
    'reactions_delete',
    'reactions_delete_confirmation',
    'reactions_reupload',
    'description'
], 'local_reactforum');
$reactionsdata = local_reactforum_getreactionsjson($forum->id, $discussion ? $discussion->id : null, isset($forummetadata) ? $forummetadata : null);
$PAGE->requires->js_call_amd('local_reactforum/managereactions', 'init', [$reactionsdata]);

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
