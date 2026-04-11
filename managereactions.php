<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Reaction settings management page for a forum or discussion.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$forumid = optional_param('f', 0, PARAM_INT);
$discussionid = optional_param('d', 0, PARAM_INT);

if (!$forumid && !$discussionid) {
    throw new core\exception\moodle_exception('Invalid parameters');
}

$PAGE->set_url('/local/reactforum/managereactions.php', ['f' => $forumid, 'd' => $discussionid]);

$discussion = $discussionid ? $DB->get_record('forum_discussions', ['id' => $discussionid], '*', MUST_EXIST) : null;
if ($discussion) {
    $forumid = $discussion->forum;
    $forummetadata = local_reactforum_getreactionmetadata($forumid);
    if ($forummetadata && $forummetadata->reactiontype != 'discussion') {
        throw new core\exception\moodle_exception('Invalid reaction type');
    }
}

$forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);

$course = get_course($forum->course);
require_login($course);

$cm = get_coursemodule_from_instance('forum', $forum->id, $course->id);
if (!$cm) {
    throw new core\exception\moodle_exception('error_invalidforum', 'local_reactforum');
}

$modcontext = context_module::instance($cm->id);

if ($discussion) {
    if (!local_reactforum_caneditdiscussion($discussion, $modcontext)) {
        throw new \core\exception\moodle_exception(
            'nopermissions',
            'error',
            '',
            get_string('reactionsettings', 'local_reactforum')
        );
    }
} else {
    /** @var context $modcontext */
    $modcontext;
    require_capability('local/reactforum:forumconfig', $modcontext);
}

$form = new \local_reactforum\form\reactionsettings_form($forum->id, $discussion ? $discussion->id : null);
$redirecturl = $discussionid
    ? new \core\url('/mod/forum/discuss.php', ['d' => $discussionid])
    : new \core\url('/mod/forum/view.php', ['f' => $forumid]);

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
    $PAGE->navbar->add($discussion->name, new \core\url('/mod/forum/discuss.php', ['d' => $discussion->id]));
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
    'description',
], 'local_reactforum');
$currentmetadata = isset($forummetadata) ? $forummetadata : null;
$reactionsdata = local_reactforum_getreactionsjson($forum->id, $discussion ? $discussion->id : null, $currentmetadata);
$PAGE->requires->js_call_amd('local_reactforum/managereactions', 'init', [$reactionsdata]);

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
