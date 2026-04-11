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
 * AJAX endpoint for submitting a reaction to a forum post.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$postid = required_param('post', PARAM_INT);
$reactionid = required_param('reaction', PARAM_INT);
$post = $DB->get_record('forum_posts', ['id' => $postid], '*', MUST_EXIST);
$reaction = $DB->get_record('reactforum_buttons', ['id' => $reactionid], '*', MUST_EXIST);
$discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
$forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

/** @var context $context */
$context;
require_login($course, false, $cm);
require_course_login($course, true, $cm);
require_capability('mod/forum:viewdiscussion', $context);

header('Content-Type: application/json');

$reactionmetadata = local_reactforum_getreactionmetadata($forum->id, $discussion->id);
if (!$reactionmetadata) {
    $reactionmetadata = local_reactforum_getreactionmetadata($forum->id);
}
if (!$reactionmetadata) {
    throw new \core\exception\moodle_exception('Cannot get reation metadata');
}

if ($post->userid == $USER->id) {
    throw new \core\exception\moodle_exception('Cannot react to own post');
}
if (!$reactionmetadata->reactionallreplies && $discussion->firstpost != $post->id) {
    throw new \core\exception\moodle_exception('Cannot react to replies');
}

$userreaction = $DB->get_record('reactforum_reacted', ['post' => $post->id, 'userid' => $USER->id]);
if (!$userreaction) {
    $userreaction = new stdClass();
    $userreaction->post = $post->id;
    $userreaction->reaction = $reaction->id;
    $userreaction->userid = $USER->id;
    if (!$DB->delete_records('reactforum_reacted', ['post' => $post->id, 'userid' => $USER->id])) {
        throw new \core\exception\moodle_exception('Cannot clear reactions before reacting');
    }
    if (!$DB->insert_record('reactforum_reacted', $userreaction)) {
        throw new \core\exception\moodle_exception('Cannot react');
    }
    echo json_encode(local_reactforum_getpostreactionsdata($reactionmetadata, $post->id));
    exit;
}
if (!$reactionmetadata->changeable) {
    throw new \core\exception\moodle_exception('Cannot change the reaction in a delayed counter type');
}
if ($userreaction->reaction == $reaction->id) {
    if (!$DB->delete_records('reactforum_reacted', ['post' => $post->id, 'userid' => $USER->id])) {
        throw new \core\exception\moodle_exception('Cannot clear reactions');
    }
    echo json_encode(local_reactforum_getpostreactionsdata($reactionmetadata, $post->id));
    exit;
}
$userreaction->reaction = $reaction->id;
if (!$DB->update_record('reactforum_reacted', $userreaction)) {
    throw new \core\exception\moodle_exception('Cannot update reaction');
}
echo json_encode(local_reactforum_getpostreactionsdata($reactionmetadata, $post->id));
exit;
