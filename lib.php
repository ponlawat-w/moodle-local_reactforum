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
 * Library functions for local_reactforum.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a "Reaction settings" node to the forum module settings navigation.
 *
 * @param navigation_node $settingsnav
 * @param context $context
 * @return void
 */
function local_reactforum_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;
    $context = $PAGE->context;
    if (!($context instanceof \core\context\module)) {
        return;
    }

    $cm = get_coursemodule_from_id('forum', $context->instanceid);
    if (!$cm) {
        return;
    }

    /** @var context $context */
    $context;
    if (has_capability('local/reactforum:forumconfig', $context)) {
        $vaultfactory = mod_forum\local\container::get_vault_factory();
        $legacydatamapperfactory = mod_forum\local\container::get_legacy_data_mapper_factory();
        $forumvault = $vaultfactory->get_forum_vault();
        $forumentity = $forumvault->get_from_id($PAGE->cm->instance);
        $forumobject = $legacydatamapperfactory->get_forum_data_mapper()->to_legacy_object($forumentity);

        $modulesettings = $settingsnav->find('modulesettings', null);
        if ($modulesettings) {
            $url = new \core\url('/local/reactforum/managereactions.php', ['f' => $forumobject->id]);
            $node = $modulesettings->create(
                get_string('reactionsettings', 'local_reactforum'),
                $url,
                navigation_node::NODETYPE_LEAF,
                null,
                'reactforum_forumconfig'
            );
            $modulesettings->add_node($node);
        }
    }
}

/**
 * Returns whether the current user can edit discussion-level reaction settings.
 *
 * @param stdClass $discussion forum_discussions record
 * @param context $modcontext
 * @return bool
 */
function local_reactforum_caneditdiscussion($discussion, $modcontext) {
    global $USER;
    $reactionsetting = local_reactforum_getreactionsetting($discussion->forum);
    if ($reactionsetting && $reactionsetting->reactiontype != 'discussion') {
        return false;
    }
    return $discussion->userid == $USER->id || has_capability('mod/forum:editanypost', $modcontext);
}

/**
 * Fetches the reaction setting record for a forum or discussion.
 *
 * Pass $discussionid to get discussion-level override; pass only $forumid for forum-level.
 *
 * @param int|null $forumid
 * @param int|null $discussionid
 * @return stdClass|false
 */
function local_reactforum_getreactionsetting($forumid = null, $discussionid = null) {
    global $DB;
    if ($discussionid) {
        return $DB->get_record('local_reactforum_settings', ['discussion' => $discussionid]);
    }
    if ($forumid) {
        return $DB->get_record('local_reactforum_settings', ['forum' => $forumid, 'discussion' => null]);
    }
    throw new core\exception\moodle_exception('error_invalidforum', 'local_reactforum');
}

/**
 * Returns a JSON string describing the configured reactions for a forum or discussion.
 *
 * @param int $forumid
 * @param int|null $discussionid
 * @param stdClass|null $setting pre-fetched setting record (optional)
 * @return string JSON-encoded reactions array or null
 */
function local_reactforum_getreactionsjson($forumid, $discussionid, $reactionsetting = null) {
    global $DB;
    if (!$reactionsetting) {
        $reactionsetting = local_reactforum_getreactionsetting($forumid, $discussionid);
    }
    if (!$reactionsetting) {
        return json_encode(null);
    }
    $reactions = $DB->get_records('local_reactforum_reactions', ['forum' => $forumid, 'discussion' => $discussionid]);

    $isimage = $reactionsetting->reactiontype === 'image';
    if ($isimage) {
        $context = local_reactforum_getmodcontextfromforumid($forumid);
        $fs = get_file_storage();
    }

    $values = [];
    foreach ($reactions as $reaction) {
        $value = ['id' => $reaction->id, 'value' => $reaction->reaction];
        if ($isimage) {
            $files = $fs->get_area_files($context->id, 'local_reactforum', 'reactions', $reaction->id, 'id', false);
            $file = reset($files);
            $value['imageurl'] = $file
                ? \core\url::make_pluginfile_url(
                    $context->id, 'local_reactforum', 'reactions',
                    $reaction->id, '/', $file->get_filename()
                )->out(false)
                : null;
        }
        $values[] = $value;
    }

    return json_encode([
        'type' => $reactionsetting->reactiontype,
        'reactions' => $values,
    ]);
}

/**
 * Removes all temporary reaction image files belonging to the current user.
 *
 * @param file_storage $fs
 * @return void
 */
function local_reactforum_cleartemp($fs) {
    global $USER;

    $usercontext = \context_user::instance($USER->id);
    $files = $fs->get_area_files($usercontext->id, 'local_reactforum', 'temp');
    foreach ($files as $file) {
        $file->delete();
    }
}

/**
 * Moves a temporary reaction image file into the permanent file area for a reaction button.
 *
 * @param file_storage $fs
 * @param int $contextid context id of the forum module
 * @param stored_file $tempfile the temporary file to move
 * @param int $reactionid id of the local_reactforum_reactions record
 * @return stored_file
 */
function local_reactforum_savetemp($fs, $contextid, $tempfile, $reactionid) {
    $files = $fs->get_area_files($contextid, 'local_reactforum', 'reactions', $reactionid);
    foreach ($files as $file) {
        $file->delete();
    }

    $fileinfo = [
        'contextid' => $contextid,
        'component' => 'local_reactforum',
        'filearea' => 'reactions',
        'itemid' => $reactionid,
        'filepath' => '/',
        'filename' => $tempfile->get_filename(),
    ];

    $reactionfile = $fs->create_file_from_storedfile($fileinfo, $tempfile);

    $tempfile->delete();

    return $reactionfile;
}

/**
 * Returns the module context for the given forum record.
 *
 * @param stdClass $forum forum record
 * @return context_module
 */
function local_reactforum_getmodcontextfromforum($forum) {
    $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course);
    return context_module::instance($cm->id);
}

/**
 * Returns the module context for the given forum id.
 *
 * @param int $forumid
 * @return context_module
 */
function local_reactforum_getmodcontextfromforumid($forumid) {
    global $DB;
    $forum = $DB->get_record('forum', ['id' => $forumid]);
    if (!$forum) {
        throw new core\exception\moodle_exception('error_invalidforum', 'local_reactforum');
    }
    return local_reactforum_getmodcontextfromforum($forum);
}

/**
 * Deletes a reaction button and all associated user reactions and image files.
 *
 * @param int $reactionid id of the local_reactforum_reactions record
 * @return bool true on success
 */
function local_reactforum_removereaction($reactionid) {
    global $DB;

    $reaction = $DB->get_record('local_reactforum_reactions', ['id' => $reactionid]);
    if (!$reaction) {
        throw new core\exception\moodle_exception('error_invalidreaction', 'local_reactforum');
    }

    $forum = $DB->get_record('forum', ['id' => $reaction->forum]);

    $fs = get_file_storage();

    $modcontext = local_reactforum_getmodcontextfromforum($forum);
    $files = $fs->get_area_files($modcontext->id, 'local_reactforum', 'reactions', $reaction->id);
    foreach ($files as $file) {
        $file->delete();
    }

    if (!$DB->delete_records('local_reactforum_user_reactions', ['reaction' => $reactionid])) {
        return false;
    }

    if (!$DB->delete_records('local_reactforum_reactions', ['id' => $reactionid])) {
        return false;
    }

    return true;
}

/**
 * Returns a data object describing one reaction button's state for one post.
 *
 * @param stdClass $setting local_reactforum_settings record
 * @param int $postid
 * @param int $reactionid
 * @param bool $reactedpost whether the current user has already reacted on this post
 * @param int $postuser userid of the post author
 * @return stdClass with properties: reacted (bool), count (int|null), enabled (bool)
 */
function local_reactforum_getpostreactiondata($reactionsetting, $postid, $reactionid, $reactedpost, $postuser) {
    global $DB, $USER;
    $result = new stdClass();
    $result->reacted = $DB->count_records(
        'local_reactforum_user_reactions',
        ['post' => $postid, 'reaction' => $reactionid, 'userid' => $USER->id]
    ) > 0;
    $canseecounter = !$reactionsetting->delayedcounter || $reactedpost || $postuser == $USER->id;
    $result->count = $canseecounter
        ? $DB->count_records('local_reactforum_user_reactions', ['post' => $postid, 'reaction' => $reactionid])
        : null;
    $result->enabled = ($postuser != $USER->id) &&
        ($reactionsetting->changeable || (!$reactionsetting->changeable && !$reactedpost));
    return $result;
}

/**
 * Returns a map of reaction button id → per-reaction state for a single post.
 *
 * Returns an empty array when reactions are not applicable for the post (e.g. reply).
 *
 * @param stdClass $setting local_reactforum_settings record
 * @param int $postid
 * @return array<int, stdClass>
 */
function local_reactforum_getpostreactionsdata($reactionsetting, $postid) {
    global $DB, $USER;
    $reactions = $DB->get_records('local_reactforum_reactions', ['forum' => $reactionsetting->forum, 'discussion' => $reactionsetting->discussion]);
    $post = $DB->get_record('forum_posts', ['id' => $postid]);
    if (!$reactionsetting->reactionallreplies && $post->parent) {
        return [];
    }
    $reactedpost = $DB->count_records('local_reactforum_user_reactions', ['post' => $post->id, 'userid' => $USER->id]) > 0;
    $results = [];
    foreach ($reactions as $reaction) {
        $results[$reaction->id] = local_reactforum_getpostreactiondata(
            $reactionsetting,
            $postid,
            $reaction->id,
            $reactedpost,
            $post->userid
        );
    }
    return $results;
}

/**
 * Builds a complete reactions data object for a discussion (setting + buttons + per-post states).
 *
 * Returns null when no reaction configuration is found for the discussion.
 *
 * @param int $discussionid
 * @return stdClass|null
 */
function local_reactforum_getdiscussionreactionsdata($discussionid) {
    global $DB;
    $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
    if (!$discussion) {
        throw new core\exception\moodle_exception('error_invaliddiscussion', 'local_reactforum');
    }
    $result = new stdClass();
    $result->setting = local_reactforum_getreactionsetting($discussion->forum, $discussion->id);
    if (!$result->setting) {
        $result->setting = local_reactforum_getreactionsetting($discussion->forum);
    }
    if (!$result->setting) {
        return null;
    }
    $result->reactions = $result->setting->discussion
        ? array_values($DB->get_records('local_reactforum_reactions', ['discussion' => $discussion->id]))
        : array_values($DB->get_records('local_reactforum_reactions', ['forum' => $discussion->forum]));
    $result->posts = [];
    $posts = $DB->get_records('forum_posts', ['discussion' => $discussionid]);
    foreach ($posts as $post) {
        if (!$result->setting->reactionallreplies && $post->parent) {
            continue;
        }
        $result->posts[$post->id] = local_reactforum_getpostreactionsdata($result->setting, $post->id);
    }
    return $result;
}

/**
 * Serves stored reaction image files via pluginfile.php.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if file not found
 */
function local_reactforum_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    require_login($course, false, $cm);

    if ($filearea !== 'reactions') {
        return false;
    }
    if (isguestuser()) {
        return false;
    }
    require_capability('mod/forum:viewdiscussion', $context);

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? ('/' . implode('/', $args) . '/') : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_reactforum', 'reactions', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Enqueues the CSS, JS strings, and AMD module needed to render reactions on a discuss.php page.
 *
 * @return void
 */
function local_reactforum_initreactions() {
    global $PAGE;
    $PAGE->requires->css('/local/reactforum/styles.css');
    $PAGE->requires->strings_for_js(['reactions'], 'local_reactforum');
    $PAGE->requires->js_call_amd('local_reactforum/reactions', 'init', [required_param('d', PARAM_INT)]);
}
