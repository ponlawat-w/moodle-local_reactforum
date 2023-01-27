<?php
function local_reactforum_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;
    $context = $PAGE->context;
    if (!($context instanceof context_module)) {
        return;
    }

    $cm = get_coursemodule_from_id('forum', $context->instanceid);
    if (!$cm) {
        return;
    }

    if ($PAGE->url->get_path() == '/mod/forum/discuss.php') {
        local_reactforum_initreactions();
    }

    if (has_capability('local/reactforum:forumconfig', $context)) {
        $vaultfactory = mod_forum\local\container::get_vault_factory();
        $legacydatamapperfactory = mod_forum\local\container::get_legacy_data_mapper_factory();
        $forumvault = $vaultfactory->get_forum_vault();
        $forumentity = $forumvault->get_from_id($PAGE->cm->instance);
        $forumobject = $legacydatamapperfactory->get_forum_data_mapper()->to_legacy_object($forumentity);    

        $modulesettings = $settingsnav->find('modulesettings', null);
        if ($modulesettings) {
            $url = new moodle_url('/local/reactforum/managereactions.php', ['f' => $forumobject->id]);
            $node = $modulesettings->create(get_string('reactionsettings', 'local_reactforum'), $url, navigation_node::NODETYPE_LEAF, null, 'reactforum_forumconfig');
            $modulesettings->add_node($node);
        }
    }
}

function local_reactforum_initiatedependencies($PAGE) {
    $PAGE->requires->css('/local/reactforum/styles.css');
    $PAGE->requires->jquery();
    $PAGE->requires->js('/local/reactforum/formscript.js');
    $PAGE->requires->strings_for_js([
        'reactionstype_change_confirmation',
        'reactions_add',
        'reactions_changeimage',
        'reactions_selectfile',
        'reactions_cancel',
        'reactions_delete',
        'reactions_delete_confirmation',
        'reactions_reupload'
    ], 'local_reactforum');
}

function local_reactforum_caneditdiscussion($discussion, $modcontext) {
    global $USER;
    $forummetadata = local_reactforum_getreactionmetadata($discussion->forum);
    if ($forummetadata && $forummetadata->reactiontype != 'discussion') {
        return false;
    }
    return $discussion->userid == $USER->id || has_capability('mod/forum:editanypost', $modcontext);
}

function local_reactforum_getreactionmetadata($forumid = null, $discussionid = null) {
    global $DB;
    if ($discussionid) {
        return $DB->get_record('reactforum_metadata', ['discussion' => $discussionid]);
    }
    if ($forumid) {
        return $DB->get_record('reactforum_metadata', ['forum' => $forumid, 'discussion' => null]);
    }
    throw new moodle_exception('Invalid arguments');
}

function local_reactforum_movedrafttotemp($fs, $drafturl) {
    global $USER;

    $filepath_exploded_temp = explode('draftfile.php/', $drafturl);
    $filepath_exploded = explode('/', $filepath_exploded_temp[1]);

    $draftinfo = [
        'contextid' => $filepath_exploded[0],
        'component' => $filepath_exploded[1],
        'filearea' => $filepath_exploded[2],
        'itemid' => $filepath_exploded[3],
        'filename' => urldecode($filepath_exploded[4])
    ];

    $draftfile = $fs->get_file($draftinfo['contextid'], $draftinfo['component'], $draftinfo['filearea'], $draftinfo['itemid'], '/', $draftinfo['filename']);
    if (!$draftfile) {
        throw new moodle_exception('draftnotfound');
    }

    $tempfileinfo = [
        'contextid' => 7,
        'component' => 'user',
        'filearea' => 'local_reactforum_temp',
        'itemid' => time(),
        'filepath' => '/' . $USER->id . '/',
        'filename' => $draftinfo['filename']
    ];

    while ($fs->file_exists(0, 'local_reactforum', 'safetemp', $tempfileinfo['itemid'], '/', $tempfileinfo['filename'])) {
        $tempfileinfo['itemid']++;
    }

    return $fs->create_file_from_storedfile($tempfileinfo, $draftfile);
}

function local_reactforum_cleartemp($fs) {
    global $USER;

    $files = $fs->get_area_files(7, 'user', 'local_reactforum_temp');

    foreach ($files as $file) {
        if ($file->get_filepath() == '/' . $USER->id . '/') {
            $file->delete();
        }
    }
}

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
        'filename' => $tempfile->get_filename()
    ];

    $reactionfile = $fs->create_file_from_storedfile($fileinfo, $tempfile);

    $tempfile->delete();

    return $reactionfile;
}

function local_reactforum_getmodcontextfromforum($forum) {
    $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course);
    return context_module::instance($cm->id);
}

function local_reactforum_getmodcontextfromforumid($forumid) {
    global $DB;
    $forum = $DB->get_record('forum', ['id' => $forumid]);
    if (!$forum) {
        throw new moodle_exception('Invalid forum ID');
    }
    return local_reactforum_getmodcontextfromforum($forum);
}

function local_reactforum_removereaction($reactionid) {
    global $DB;

    $reaction = $DB->get_record('reactforum_reactions', ['id' => $reactionid]);
    if (!$reaction) {
        throw new moodle_exception('Invalid reaction ID');
    }

    $forum = $DB->get_record('forum', ['id' => $reaction->forum]);

    $fs = get_file_storage();

    $files = $fs->get_area_files(local_reactforum_getmodcontextfromforum($forum)->id, 'local_reactforum', 'reactions', $reaction->id);
    foreach ($files as $file) {
        $file->delete();
    }

    if (!$DB->delete_records('reactforum_user_reactions', ['reaction' => $reactionid])) {
        return false;
    }

    if (!$DB->delete_records('reactforum_reactions', ['id' => $reactionid])) {
        return false;
    }

    return true;
}

function local_reactforum_getpostreactiondata($metadata, $postid, $reactionid, $reactedpost, $postuser) {
    global $DB, $USER;
    $result = new stdClass();
    $result->reacted = $DB->count_records('reactforum_user_reactions', ['post' => $postid, 'reaction' => $reactionid, 'user' => $USER->id]) > 0;
    $result->count = !$metadata->delayedcounter || $reactedpost || $postuser == $USER->id
        ? $DB->count_records('reactforum_user_reactions', ['post' => $postid, 'reaction' => $reactionid])
        : null;
    $result->enabled = ($postuser != $USER->id) && (!$metadata->delayedcounter || ($metadata->delayedcounter && !$reactedpost));
    return $result;
}

function local_reactforum_getpostreactionsdata($metadata, $postid) {
    global $DB, $USER;
    $reactions = $DB->get_records('reactforum_reactions', ['forum' => $metadata->forum, 'discussion' => $metadata->discussion]);
    $post = $DB->get_record('forum_posts', ['id' => $postid]);
    if (!$metadata->reactionallreplies && $post->parent) {
        return [];
    }
    $reactedpost = $DB->count_records('reactforum_user_reactions', ['post' => $post->id, 'user' => $USER->id]) > 0;
    $results = [];
    foreach ($reactions as $reaction) {
        $results[$reaction->id] = local_reactforum_getpostreactiondata($metadata, $postid, $reaction->id, $reactedpost, $post->userid);
    }
    return $results;
}

function local_reactforum_getdiscussionreactionsdata($discussionid) {
    global $DB;
    $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
    if (!$discussion) {
        throw new moodle_exception('Invalid discussion ID');
    }
    $result = new stdClass();
    $result->metadata = local_reactforum_getreactionmetadata($discussion->forum, $discussion->id);
    if (!$result->metadata) {
        $result->metadata = local_reactforum_getreactionmetadata($discussion->forum);
    }
    if (!$result->metadata) {
        return null;
    }
    $result->reactions = $result->metadata->discussion
        ? array_values($DB->get_records('reactforum_reactions', ['discussion' => $discussion->id]))
        : array_values($DB->get_records('reactforum_reactions', ['forum' => $discussion->forum]));
    $result->posts = [];
    $posts = $DB->get_records('forum_posts', ['discussion' => $discussionid]);
    foreach ($posts as $post) {
        if (!$result->metadata->reactionallreplies && $post->parent) {
            continue;
        }
        $result->posts[$post->id] = local_reactforum_getpostreactionsdata($result->metadata, $post->id);
    }
    return $result;
}

function local_reactforum_initreactions() {
    global $PAGE;
    $PAGE->requires->strings_for_js(['reactions'], 'local_reactforum');
    $PAGE->requires->js('/local/reactforum/script.js?d=' . required_param('d', PARAM_INT));
}
