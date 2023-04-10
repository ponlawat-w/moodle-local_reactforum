<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
$reactionid = required_param('id', PARAM_INT);
$reaction = $DB->get_record('reactforum_buttons', ['id' => $reactionid], '*', MUST_EXIST);
$metadata = $DB->get_record('reactforum_metadata', ['forum' => $reaction->forum, 'discussion' => $reaction->discussion]);
$forum = $DB->get_record('forum', ['id' => $reaction->forum]);
$course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_course_login($course, true, $cm);
require_capability('mod/forum:viewdiscussion', $context);
$return = new stdClass();
if (is_guest($context, $USER)) {
    // is_guest should be used here as this also checks whether the user is a guest in the current course.
    // Guests and visitors cannot subscribe - only enrolled users.
    throw new moodle_exception('GUEST_ERROR', 'mod_reactforum');
}
if ($metadata->reactiontype != 'image') {
    throw new moodle_exception('REACTIONTYPE_ERROR', 'mod_reactforum');
}
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'local_reactforum', 'reactions', $reactionid);
if (!count($files)) {
    http_response_code(404);
    exit;
}
session_write_close();
foreach ($files as $file) if($file->is_valid_image()) {
    header("Content-type: " . $file->get_mimetype());
    header("filename=" . $file->get_filename());
    echo $file->get_content();
    exit;
}
