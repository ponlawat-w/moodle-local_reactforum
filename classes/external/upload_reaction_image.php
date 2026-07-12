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
 * External function: upload_reaction_image
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reactforum\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

/**
 * Moves a draft file to the plugin temporary area and returns its stored file id.
 *
 * This replaces the legacy imageuploaded.php endpoint.
 */
class upload_reaction_image extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'draftitemid' => new external_value(PARAM_INT, 'Draft item id of the uploaded file'),
            'filename' => new external_value(PARAM_FILE, 'Filename of the uploaded file'),
            'forumid' => new external_value(PARAM_INT, 'Forum id the reaction image is configured for', VALUE_DEFAULT, 0),
            'discussionid' => new external_value(PARAM_INT, 'Discussion id for discussion-level reactions', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'Course id for a not-yet-created forum', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Copies a draft file to the plugin's temp area and returns the stored_file id.
     *
     * @param int $draftitemid
     * @param string $filename
     * @param int $forumid
     * @param int $discussionid
     * @param int $courseid
     * @return int stored_file id
     */
    public static function execute(
        int $draftitemid,
        string $filename,
        int $forumid = 0,
        int $discussionid = 0,
        int $courseid = 0
    ): int {
        global $DB, $USER;

        [
            'draftitemid' => $draftitemid,
            'filename' => $filename,
            'forumid' => $forumid,
            'discussionid' => $discussionid,
            'courseid' => $courseid,
        ] = self::validate_parameters(
            self::execute_parameters(),
            [
                'draftitemid' => $draftitemid,
                'filename' => $filename,
                'forumid' => $forumid,
                'discussionid' => $discussionid,
                'courseid' => $courseid,
            ]
        );

        // Resolve the execution context and enforce the same permissions as managereactions.php:
        // reaction images can only be uploaded by users allowed to configure reactions for the
        // target forum/discussion (or to add activities to the course for a not-yet-created forum).
        $discussion = $discussionid ? $DB->get_record('forum_discussions', ['id' => $discussionid], '*', MUST_EXIST) : null;
        if ($discussion) {
            $forumid = $discussion->forum;
        }

        if ($forumid) {
            $forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);
            $course = get_course($forum->course);
            $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
            /** @var \context $context */
            $context = \core\context\module::instance($cm->id);
            self::validate_context($context);
            if ($discussion) {
                if (!local_reactforum_caneditdiscussion($discussion, $context)) {
                    throw new \core\exception\moodle_exception(
                        'nopermissions',
                        'error',
                        '',
                        get_string('reactionsettings', 'local_reactforum')
                    );
                }
            } else {
                require_capability('local/reactforum:forumconfig', $context);
            }
        } else if ($courseid) {
            // The forum is being created, so no module context exists yet.
            /** @var \context $context */
            $context = \core\context\course::instance($courseid);
            self::validate_context($context);
            require_capability('moodle/course:manageactivities', $context);
        } else {
            throw new \core\exception\moodle_exception('error_invalidparams', 'local_reactforum');
        }

        $usercontext = \core\context\user::instance($USER->id);

        $fs = get_file_storage();

        $draftfile = $fs->get_file(
            $usercontext->id,
            'user',
            'draft',
            $draftitemid,
            '/',
            $filename
        );

        if (!$draftfile || $draftfile->is_directory()) {
            throw new \core\exception\moodle_exception('error_invalidreaction', 'local_reactforum');
        }

        // Find a unique itemid in the temp area.
        $tempitemid = time();
        while ($fs->file_exists($usercontext->id, 'local_reactforum', 'temp', $tempitemid, '/', $filename)) {
            $tempitemid++;
        }

        $tempfileinfo = [
            'contextid' => $usercontext->id,
            'component' => 'local_reactforum',
            'filearea' => 'temp',
            'itemid' => $tempitemid,
            'filepath' => '/',
            'filename' => $filename,
        ];

        $tempfile = $fs->create_file_from_storedfile($tempfileinfo, $draftfile);

        return (int) $tempfile->get_id();
    }

    /**
     * Describes the return value.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_INT, 'Stored file id of the temporary reaction image');
    }
}
