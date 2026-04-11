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
 * Serves a stored reaction image file.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
$reactionid = required_param('id', PARAM_INT);
$reaction = $DB->get_record('reactforum_buttons', ['id' => $reactionid], '*', MUST_EXIST);
$metadata = $DB->get_record('reactforum_metadata', ['forum' => $reaction->forum, 'discussion' => $reaction->discussion]);
$forum = $DB->get_record('forum', ['id' => $reaction->forum]);
$course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
$context = \core\context\module::instance($cm->id);

/** @var context $context */
$context;
require_login($course, false, $cm);
require_course_login($course, true, $cm);
require_capability('mod/forum:viewdiscussion', $context);

$return = new stdClass();
if (is_guest($context, $USER)) {
    // Guests cannot view reaction images.
    throw new core\exception\moodle_exception('error_cannotreactownpost', 'local_reactforum');
}
if ($metadata->reactiontype != 'image') {
    throw new core\exception\moodle_exception('error_invalidreaction', 'local_reactforum');
}
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'local_reactforum', 'reactions', $reactionid);
if (!count($files)) {
    http_response_code(404);
    exit;
}
session_write_close();
foreach ($files as $file) {
    if ($file->is_valid_image()) {
        header('Content-Type: "' . $file->get_mimetype() . '"');
        header('Content-Disposition: inline; filename="' . $file->get_filename() . '"');
        echo $file->get_content();
        exit;
    }
}
