<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$discussionid = required_param('id', PARAM_INT);
$discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], '*', MUST_EXIST);
$forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_course_login($course, true, $cm);
require_capability('mod/forum:viewdiscussion', $context);

$reactionsdata = local_reactforum_getdiscussionreactionsdata($discussion->id);
$reactionsdata->canmanage = local_reactforum_caneditdiscussion($discussion, $context);
header('Content-Type: application/json');
echo json_encode($reactionsdata);
