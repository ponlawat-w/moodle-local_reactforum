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
 * Adds reaction settings form elements to the given mform.
 *
 * Shared by both the standalone managereactions form and the module settings injection.
 * Does NOT add a header, hidden fields, or action buttons — those are caller-specific.
 *
 * @param \MoodleQuickForm $mform
 * @param \stdClass|null $reactionsetting existing local_reactforum_settings record, or null
 * @param bool $includediscussiontype whether to include the "discussion" radio option
 * @return void
 */
function local_reactforum_applytoform(
    \MoodleQuickForm $mform,
    \stdClass|false|null $reactionsetting,
    bool $includediscussiontype = true
) {
    $radioarray = [
        $mform->createElement('radio', 'reactiontype', '', get_string('reactionstype_none', 'local_reactforum'), 'none'),
        $mform->createElement('radio', 'reactiontype', '', get_string('reactionstype_text', 'local_reactforum'), 'text'),
        $mform->createElement('radio', 'reactiontype', '', get_string('reactionstype_image', 'local_reactforum'), 'image'),
    ];
    if ($includediscussiontype) {
        $radioarray[] = $mform->createElement(
            'radio',
            'reactiontype',
            '',
            get_string('reactionstype_discussion', 'local_reactforum'),
            'discussion'
        );
    }
    $mform->addGroup($radioarray, 'reactiontype', get_string('reactionstype', 'local_reactforum'), ['<br>'], false);
    $mform->setDefault('reactiontype', $reactionsetting ? $reactionsetting->reactiontype : 'none');

    $mform->addGroup([], 'reactions', get_string('reactionsbuttons', 'local_reactforum'), ['<br>'], false);

    $mform->addElement('filepicker', 'reactionimage', '', null, ['maxbytes' => 0, 'accepted_types' => ['image']]);

    $mform->addElement('checkbox', 'reactionallreplies', get_string('reactions_allreplies', 'local_reactforum'));
    $mform->addHelpButton('reactionallreplies', 'reactions_allreplies', 'local_reactforum');
    $mform->setDefault('reactionallreplies', $reactionsetting ? ($reactionsetting->reactionallreplies ? true : false) : false);

    $mform->addElement('checkbox', 'delayedcounter', get_string('reactions_delayedcounter', 'local_reactforum'));
    $mform->addHelpButton('delayedcounter', 'reactions_delayedcounter', 'local_reactforum');
    $mform->setDefault('delayedcounter', $reactionsetting && $reactionsetting->delayedcounter ? true : false);

    $mform->addElement('checkbox', 'changeable', get_string('reactions_changeable', 'local_reactforum'));
    $mform->addHelpButton('changeable', 'reactions_changeable', 'local_reactforum');
    $mform->setDefault('changeable', $reactionsetting ? ($reactionsetting->changeable ? true : false) : true);
}

/**
 * Loads the CSS, JS strings, and AMD module required for the reaction management UI.
 *
 * Shared by managereactions.php and the module settings form injection.
 *
 * @param int|null $forumid
 * @param int|null $discussionid
 * @return void
 */
function local_reactforum_requirejsformanagereactions(?int $forumid, ?int $discussionid) {
    global $PAGE;
    /** @var \moodle_page $PAGE */
    $PAGE;
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
    $reactionsdata = $forumid ? local_reactforum_getreactionsjson($forumid, $discussionid) : json_encode(null);
    $PAGE->requires->js_call_amd('local_reactforum/managereactions', 'init', [$reactionsdata]);
}

/**
 * Processes reaction settings form data for a given forum or discussion.
 *
 * Shared by reactionsettings_form::process() and coursemodule_edit_post_actions.
 * Handles upsert of the settings record, reaction-type change cleanup, and
 * add/edit/delete of individual reaction buttons.
 *
 * @param int $forumid
 * @param int|null $discussionid null for forum-level settings
 * @param \stdClass $data form data with reactiontype, reactionallreplies, delayedcounter, changeable
 * @param \context_module|null $modcontext resolved automatically when null
 * @return bool true on success
 */
function local_reactforum_processreactionsdata(
    int $forumid,
    ?int $discussionid,
    \stdClass $data,
    ?\context_module $modcontext = null
): bool {
    global $DB;
    /** @var \moodle_database $DB */
    $DB;

    if (!$modcontext) {
        $modcontext = local_reactforum_getmodcontextfromforumid($forumid);
    }

    $reactionsetting = local_reactforum_getreactionsetting($forumid, $discussionid);

    // If the reaction type changed, remove all existing reactions and settings records.
    if ($reactionsetting && $data->reactiontype !== $reactionsetting->reactiontype) {
        $reactions = $DB->get_records(
            'local_reactforum_reactions',
            ['forum' => $forumid, 'discussion' => $discussionid]
        );
        foreach ($reactions as $reaction) {
            local_reactforum_removereaction($reaction->id);
        }
        $DB->delete_records('local_reactforum_settings', ['forum' => $forumid, 'discussion' => $discussionid]);

        if (!$discussionid) {
            // Also clean up all discussion-level reactions and settings for this forum.
            $reactions = $DB->get_records('local_reactforum_reactions', ['forum' => $forumid]);
            foreach ($reactions as $reaction) {
                local_reactforum_removereaction($reaction->id);
            }
            $DB->delete_records('local_reactforum_settings', ['forum' => $forumid]);
        }

        $reactionsetting = null;
    }

    // Upsert the settings record.
    if ($reactionsetting) {
        $reactionsetting->reactiontype = $data->reactiontype;
        $reactionsetting->reactionallreplies = isset($data->reactionallreplies) ? $data->reactionallreplies : 0;
        $reactionsetting->delayedcounter = isset($data->delayedcounter) ? $data->delayedcounter : 0;
        $reactionsetting->changeable = isset($data->changeable) ? $data->changeable : 0;
        if (!$DB->update_record('local_reactforum_settings', $reactionsetting)) {
            throw new \core\exception\moodle_exception('error_invaliddiscussion', 'local_reactforum');
        }
    } else {
        $reactionsetting = new \stdClass();
        $reactionsetting->forum = $forumid;
        $reactionsetting->discussion = $discussionid;
        $reactionsetting->reactiontype = $data->reactiontype;
        $reactionsetting->reactionallreplies = $data->reactionallreplies ?? 0;
        $reactionsetting->delayedcounter = $data->delayedcounter ?? 0;
        $reactionsetting->changeable = $data->changeable ?? 0;
        if (!$DB->insert_record('local_reactforum_settings', $reactionsetting)) {
            throw new \core\exception\moodle_exception('error_invaliddiscussion', 'local_reactforum');
        }
    }

    $fs = get_file_storage();

    // New text reactions.
    $newreactions = optional_param_array('reactions_new', [], PARAM_TEXT);
    if ($data->reactiontype === 'text' && $newreactions) {
        foreach ($newreactions as $reactiontxt) {
            $reactiontxt = trim($reactiontxt);
            if ($reactiontxt === '') {
                continue;
            }
            $reaction = new \stdClass();
            $reaction->forum = $forumid;
            $reaction->discussion = $discussionid;
            $reaction->reaction = $reactiontxt;
            if (!$DB->insert_record('local_reactforum_reactions', $reaction)) {
                throw new \core\exception\moodle_exception('error_invalidreaction', 'local_reactforum');
            }
        }
    }

    // New image reactions (temp file ids submitted by JS).
    $newimages = optional_param_array('reactions_new_image', [], PARAM_INT);
    if ($data->reactiontype === 'image' && $newimages) {
        $descsnew = optional_param_array('reactions_desc_new', [], PARAM_TEXT);
        foreach ($newimages as $fileid) {
            $description = $descsnew[$fileid] ?? '';
            $reaction = new \stdClass();
            $reaction->forum = $forumid;
            $reaction->discussion = $discussionid;
            $reaction->reaction = $description;
            $reactionid = $DB->insert_record('local_reactforum_reactions', $reaction);
            if (!$reactionid) {
                throw new \core\exception\moodle_exception('error_invalidreaction', 'local_reactforum');
            }
            $tempfile = $fs->get_file_by_id($fileid);
            if ($tempfile && !local_reactforum_savetemp($fs, $modcontext->id, $tempfile, $reactionid)) {
                throw new \core\exception\moodle_exception('error_invalidreaction', 'local_reactforum');
            }
        }
        local_reactforum_cleartemp($fs);
    }

    // Edit existing text reactions.
    $editreactions = optional_param_array('reactions_edit', [], PARAM_TEXT);
    if ($data->reactiontype === 'text' && $editreactions) {
        foreach ($editreactions as $reactionid => $reaction) {
            if (trim($reaction) === '') {
                continue;
            }
            $reactionobj = new \stdClass();
            $reactionobj->id = clean_param($reactionid, PARAM_INT);
            $reactionobj->reaction = $reaction;
            $DB->update_record('local_reactforum_reactions', $reactionobj);
        }
    }

    // Edit existing image reactions (replace image and/or description).
    $editimages = optional_param_array('reactions_edit_image', [], PARAM_INT);
    if ($data->reactiontype === 'image' && $editimages) {
        foreach ($editimages as $reactionid => $filetempid) {
            $reactionid = clean_param($reactionid, PARAM_INT);
            if ($filetempid > 0) {
                $tempfile = $fs->get_file_by_id($filetempid);
                if ($tempfile) {
                    local_reactforum_savetemp($fs, $modcontext->id, $tempfile, $reactionid);
                }
            }
        }
        $descedit = optional_param_array('reactions_desc_edit', [], PARAM_TEXT);
        foreach ($descedit as $reactionid => $newdescription) {
            $reactionobj = new \stdClass();
            $reactionobj->id = clean_param($reactionid, PARAM_INT);
            $reactionobj->reaction = $newdescription;
            $DB->update_record('local_reactforum_reactions', $reactionobj);
        }
    }

    // Delete reactions.
    $deletereactions = optional_param_array('reactions_delete', [], PARAM_INT);
    foreach ($deletereactions as $reactionid) {
        local_reactforum_removereaction($reactionid);
    }

    // When type is 'none', remove all reactions and the settings record.
    if ($data->reactiontype === 'none') {
        $reactions = $DB->get_records(
            'local_reactforum_reactions',
            ['forum' => $forumid, 'discussion' => $discussionid]
        );
        foreach ($reactions as $reaction) {
            local_reactforum_removereaction($reaction->id);
        }
        $DB->delete_records('local_reactforum_settings', ['forum' => $forumid, 'discussion' => $discussionid]);
    }

    return true;
}

/**
 * Injects reaction settings fields into the forum module edit form (Settings tab).
 *
 * @param \moodleform_mod $form
 * @param \MoodleQuickForm $mform
 * @return void
 */
function local_reactforum_coursemodule_standard_elements(\moodleform_mod $form, \MoodleQuickForm $mform) {
    $add = optional_param('add', null, PARAM_TEXT);
    if (!is_null($add) && $add != 'forum') {
        return;
    }
    $cm = get_coursemodule_from_id('forum', optional_param('update', 0, PARAM_INT));
    if (is_null($add) && !$cm) {
        return;
    }
    $forumid = $cm ? $cm->instance : null;
    $reactionsetting = $forumid ? local_reactforum_getreactionsetting($forumid) : null;

    $mform->addElement('header', 'local_reactforum', get_string('reactionsettings', 'local_reactforum'));
    local_reactforum_applytoform($mform, $reactionsetting, true);
    local_reactforum_requirejsformanagereactions($forumid, null);
}

/**
 * Saves reaction settings when the forum module edit form is submitted.
 *
 * @param \stdClass $moduleinfo
 * @param \stdClass $course
 * @return \stdClass
 */
function local_reactforum_coursemodule_edit_post_actions($moduleinfo, $course) {
    if ($moduleinfo->modulename != 'forum') {
        return $moduleinfo;
    }
    $data = new \stdClass();
    $data->reactiontype = optional_param('reactiontype', 'none', PARAM_TEXT);
    $data->reactionallreplies = optional_param('reactionallreplies', 0, PARAM_BOOL);
    $data->delayedcounter = optional_param('delayedcounter', 0, PARAM_BOOL);
    $data->changeable = optional_param('changeable', 0, PARAM_BOOL);
    local_reactforum_processreactionsdata($moduleinfo->id, null, $data);
    return $moduleinfo;
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
    /** @var \moodle_database $DB */
    $DB;
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
    /** @var \moodle_database $DB */
    $DB;
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
                    $context->id,
                    'local_reactforum',
                    'reactions',
                    $reaction->id,
                    '/',
                    $file->get_filename()
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
    /** @var \moodle_database $DB */
    $DB;
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
    /** @var \moodle_database $DB */
    $DB;

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

    if (!$DB->delete_records('local_reactforum_userreactions', ['reaction' => $reactionid])) {
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
    /** @var \moodle_database $DB */
    $DB;
    $result = new stdClass();
    $result->reacted = $DB->count_records(
        'local_reactforum_userreactions',
        ['post' => $postid, 'reaction' => $reactionid, 'userid' => $USER->id]
    ) > 0;
    $canseecounter = !$reactionsetting->delayedcounter || $reactedpost || $postuser == $USER->id;
    $result->count = $canseecounter
        ? $DB->count_records('local_reactforum_userreactions', ['post' => $postid, 'reaction' => $reactionid])
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
    /** @var \moodle_database $DB */
    $DB;
    $reactions = $DB->get_records(
        'local_reactforum_reactions',
        [
            'forum' => $reactionsetting->forum,
            'discussion' => $reactionsetting->discussion,
        ]
    );
    $post = $DB->get_record('forum_posts', ['id' => $postid]);
    if (!$reactionsetting->reactionallreplies && $post->parent) {
        return [];
    }
    $reactedpost = $DB->count_records('local_reactforum_userreactions', ['post' => $post->id, 'userid' => $USER->id]) > 0;
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
    /** @var \moodle_database $DB */
    $DB;
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
    /** @var \moodle_page $PAGE */
    $PAGE;
    $PAGE->requires->css('/local/reactforum/styles.css');
    $PAGE->requires->strings_for_js(['reactions'], 'local_reactforum');
    $PAGE->requires->js_call_amd('local_reactforum/reactions', 'init', [required_param('d', PARAM_INT)]);
}
