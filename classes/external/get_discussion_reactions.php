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
 * External function: get_discussion_reactions
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
 * Returns reaction setting, buttons and per-post reaction state for a discussion.
 */
class get_discussion_reactions extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'discussionid' => new external_value(PARAM_INT, 'Discussion ID'),
        ]);
    }

    /**
     * Returns all reaction data needed to render the reactions UI for a discussion.
     *
     * @param int $discussionid
     * @return array|null
     */
    public static function execute(int $discussionid): ?array {
        global $DB;

        ['discussionid' => $discussionid] = self::validate_parameters(
            self::execute_parameters(),
            ['discussionid' => $discussionid]
        );

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], '*', MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        $course = get_course($forum->course);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);

        self::validate_context($context);
        /** @var \context $context */
        $context;
        require_capability('mod/forum:viewdiscussion', $context);

        $reactionsdata = local_reactforum_getdiscussionreactionsdata($discussionid);
        if (!$reactionsdata) {
            return null;
        }

        $reactionsdata->canmanage = local_reactforum_caneditdiscussion($discussion, $context);

        // Convert setting stdClass to array.
        $setting = (array) $reactionsdata->setting;

        // Convert reactions array to plain arrays.
        $isimage = $reactionsdata->setting->reactiontype === 'image';
        if ($isimage) {
            $fs = get_file_storage();
        }
        $reactions = [];
        foreach ($reactionsdata->reactions as $reaction) {
            $entry = [
                'id' => (int) $reaction->id,
                'reaction' => (string) $reaction->reaction,
            ];
            if ($isimage) {
                $files = $fs->get_area_files($context->id, 'local_reactforum', 'reactions', $reaction->id, 'id', false);
                $file = reset($files);
                $entry['imageurl'] = $file
                    ? \core\url::make_pluginfile_url(
                        $context->id,
                        'local_reactforum',
                        'reactions',
                        $reaction->id,
                        '/',
                        $file->get_filename()
                    )->out(false)
                    : '';
            }
            $reactions[] = $entry;
        }

        // Convert per-post reaction state to plain arrays.
        $posts = [];
        foreach ($reactionsdata->posts as $postid => $postreactions) {
            $reactionstates = [];
            foreach ($postreactions as $reactionid => $state) {
                $reactionstates[] = [
                    'reactionid' => (int) $reactionid,
                    'reacted' => (bool) $state->reacted,
                    'count' => $state->count !== null ? (int) $state->count : null,
                    'enabled' => (bool) $state->enabled,
                ];
            }
            $posts[] = [
                'postid' => (int) $postid,
                'reactions' => $reactionstates,
            ];
        }

        return [
            'setting' => [
                'id' => (int) $setting['id'],
                'forum' => (int) ($setting['forum'] ?? 0),
                'discussion' => (int) ($setting['discussion'] ?? 0),
                'reactiontype' => (string) $setting['reactiontype'],
                'reactionallreplies' => (int) $setting['reactionallreplies'],
                'delayedcounter' => (int) $setting['delayedcounter'],
                'changeable' => (int) $setting['changeable'],
            ],
            'reactions' => $reactions,
            'posts' => $posts,
            'canmanage' => (bool) $reactionsdata->canmanage,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure|null
     */
    public static function execute_returns(): ?external_single_structure {
        return new external_single_structure([
            'setting' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Setting record id'),
                'forum' => new external_value(PARAM_INT, 'Forum id'),
                'discussion' => new external_value(PARAM_INT, 'Discussion id (0 = forum-level)'),
                'reactiontype' => new external_value(PARAM_ALPHA, 'Reaction type: text, image, discussion, none'),
                'reactionallreplies' => new external_value(PARAM_INT, 'Whether reactions apply to replies'),
                'delayedcounter' => new external_value(PARAM_INT, 'Whether counters are hidden until clicked'),
                'changeable' => new external_value(PARAM_INT, 'Whether users can change their reaction'),
            ]),
            'reactions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Reaction button id'),
                    'reaction' => new external_value(PARAM_TEXT, 'Button label or image description'),
                    'imageurl' => new external_value(PARAM_URL, 'URL to the reaction image (image type only)', VALUE_OPTIONAL),
                ])
            ),
            'posts' => new external_multiple_structure(
                new external_single_structure([
                    'postid' => new external_value(PARAM_INT, 'Post id'),
                    'reactions' => new external_multiple_structure(
                        new external_single_structure([
                            'reactionid' => new external_value(PARAM_INT, 'Reaction button id'),
                            'reacted' => new external_value(PARAM_BOOL, 'Whether the current user has reacted'),
                            'count' => new external_value(PARAM_INT, 'Reaction count (null when hidden)', VALUE_OPTIONAL),
                            'enabled' => new external_value(PARAM_BOOL, 'Whether the button is clickable'),
                        ])
                    ),
                ])
            ),
            'canmanage' => new external_value(PARAM_BOOL, 'Whether the current user can manage discussion reactions'),
        ]);
    }
}
