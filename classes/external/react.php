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
 * External function: react
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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Toggles a reaction on a forum post and returns the updated per-post reaction state.
 */
class react extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'Post ID'),
            'reactionid' => new external_value(PARAM_INT, 'Reaction button ID'),
        ]);
    }

    /**
     * Toggles the current user's reaction on a post and returns updated state.
     *
     * @param int $postid
     * @param int $reactionid
     * @return array map of reactionid => state
     */
    public static function execute(int $postid, int $reactionid): array {
        global $DB, $USER;

        ['postid' => $postid, 'reactionid' => $reactionid] = self::validate_parameters(
            self::execute_parameters(),
            ['postid' => $postid, 'reactionid' => $reactionid]
        );

        $post = $DB->get_record('forum_posts', ['id' => $postid], '*', MUST_EXIST);
        $reaction = $DB->get_record('local_reactforum_reactions', ['id' => $reactionid], '*', MUST_EXIST);
        $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        $course = get_course($forum->course);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        self::validate_context($context);

        /** @var \context $context */
        $context;
        require_capability('mod/forum:viewdiscussion', $context);

        /** @var \core\context\module $context */
        $context;

        $reactionsetting = local_reactforum_getreactionsetting($forum->id, $discussion->id);
        if (!$reactionsetting) {
            $reactionsetting = local_reactforum_getreactionsetting($forum->id);
        }
        if (!$reactionsetting) {
            throw new \core\exception\moodle_exception('error_invaliddiscussion', 'local_reactforum');
        }

        if ($post->userid == $USER->id) {
            throw new \core\exception\moodle_exception('error_cannotreactownpost', 'local_reactforum');
        }
        if (!$reactionsetting->reactionallreplies && $discussion->firstpost != $post->id) {
            throw new \core\exception\moodle_exception('error_repliesnotallowed', 'local_reactforum');
        }

        $userreaction = $DB->get_record('local_reactforum_userreactions', ['post' => $post->id, 'userid' => $USER->id]);

        if (!$userreaction) {
            // First reaction on this post.
            $newuserreaction = new \stdClass();
            $newuserreaction->post = $post->id;
            $newuserreaction->reaction = $reaction->id;
            $newuserreaction->userid = $USER->id;
            $DB->delete_records('local_reactforum_userreactions', ['post' => $post->id, 'userid' => $USER->id]);
            $newid = $DB->insert_record('local_reactforum_userreactions', $newuserreaction);
            \local_reactforum\event\reaction_created::createfromreacted($newid, $post->id, $context)->trigger();
        } else if (!$reactionsetting->changeable) {
            throw new \core\exception\moodle_exception('error_reactionnotchangeable', 'local_reactforum');
        } else if ($userreaction->reaction == $reaction->id) {
            // Toggle off — same button clicked again.
            $DB->delete_records('local_reactforum_userreactions', ['post' => $post->id, 'userid' => $USER->id]);
            \local_reactforum\event\reaction_deleted::createfromreacted($userreaction->id, $post->id, $context)->trigger();
        } else {
            // Switch to a different reaction.
            $userreaction->reaction = $reaction->id;
            $DB->update_record('local_reactforum_userreactions', $userreaction);
            \local_reactforum\event\reaction_created::createfromreacted($userreaction->id, $post->id, $context)->trigger();
        }

        // Build and return updated per-post reaction state.
        $postreactions = local_reactforum_getpostreactionsdata($reactionsetting, $post->id);
        $result = [];
        foreach ($postreactions as $rid => $state) {
            $result[] = [
                'reactionid' => (int) $rid,
                'reacted' => (bool) $state->reacted,
                'count' => $state->count !== null ? (int) $state->count : null,
                'enabled' => (bool) $state->enabled,
            ];
        }
        return $result;
    }

    /**
     * Describes the return value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'reactionid' => new external_value(PARAM_INT, 'Reaction button id'),
                'reacted' => new external_value(PARAM_BOOL, 'Whether the current user has reacted'),
                'count' => new external_value(PARAM_INT, 'Reaction count (null when hidden)', VALUE_OPTIONAL),
                'enabled' => new external_value(PARAM_BOOL, 'Whether the button is clickable'),
            ])
        );
    }
}
