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
 * AJAX endpoint for retrieving reaction data for a forum discussion.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$discussionid = required_param('id', PARAM_INT);
$discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], '*', MUST_EXIST);
$forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

/** @var context $context */
$context;
require_login($course, false, $cm);
require_course_login($course, true, $cm);
require_capability('mod/forum:viewdiscussion', $context);


/** @var \core\context\module $context */
$context;
$reactionsdata = local_reactforum_getdiscussionreactionsdata($discussion->id);
$reactionsdata->canmanage = local_reactforum_caneditdiscussion($discussion, $context);
header('Content-Type: application/json');
echo json_encode($reactionsdata);
