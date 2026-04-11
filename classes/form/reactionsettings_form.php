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
 * Form for configuring reaction buttons on a forum or discussion.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reactforum\form;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../lib/formslib.php');
require_once(__DIR__ . '/../../lib.php');

/**
 * Reaction settings form.
 *
 * POST field conventions used by the accompanying managereactions.js:
 *   reactions_new[]              — text values of new text reactions
 *   reactions_edit[<id>]         — updated text value for existing text reactions
 *   reactions_delete[]           — ids of reactions to delete
 *   reactions_new_image[]        — temp file ids of new image reactions
 *   reactions_desc_new[<tempid>] — description for new image reaction (keyed by temp file id)
 *   reactions_edit_image[<id>]   — temp file id to replace existing image (0 = keep current)
 *   reactions_desc_edit[<id>]    — updated description for existing image reaction
 */
class reactionsettings_form extends \moodleform {
    /** @var int|null */
    public $forumid = null;
    /** @var int|null */
    public $discussionid = null;

    /**
     * Constructor.
     *
     * @param int|null $forumid
     * @param int|null $discussionid
     */
    public function __construct($forumid = null, $discussionid = null) {
        global $DB;
        $this->forumid = $forumid;
        $this->discussionid = $discussionid;
        if ($this->discussionid) {
            $discussion = $DB->get_record('forum_discussions', ['id' => $this->discussionid]);
            if (!$discussion) {
                throw new \core\exception\moodle_exception('error_invaliddiscussion', 'local_reactforum');
            }
            $this->forumid = $discussion->forum;
        }
        parent::__construct();
    }

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $metadata = local_reactforum_getreactionmetadata($this->forumid, $this->discussionid);

        $radioarray = [
            $mform->createElement('radio', 'reactiontype', '', get_string('reactionstype_none', 'local_reactforum'), 'none'),
            $mform->createElement('radio', 'reactiontype', '', get_string('reactionstype_text', 'local_reactforum'), 'text'),
            $mform->createElement('radio', 'reactiontype', '', get_string('reactionstype_image', 'local_reactforum'), 'image'),
        ];
        if (!$this->discussionid) {
            $radioarray[] = $mform->createElement(
                'radio',
                'reactiontype',
                '',
                get_string('reactionstype_discussion', 'local_reactforum'),
                'discussion'
            );
        }
        $mform->addGroup($radioarray, 'reactiontype', get_string('reactionstype', 'local_reactforum'), ['<br>'], false);
        $mform->setDefault('reactiontype', $metadata ? $metadata->reactiontype : 'none');

        $mform->addGroup([], 'reactions', get_string('reactionsbuttons', 'local_reactforum'), ['<br>'], false);

        $mform->addElement('filepicker', 'reactionimage', '', null, ['maxbytes' => 0, 'accepted_types' => ['image']]);

        $mform->addElement('checkbox', 'reactionallreplies', get_string('reactions_allreplies', 'local_reactforum'));
        $mform->addHelpButton('reactionallreplies', 'reactions_allreplies', 'local_reactforum');
        $mform->setDefault('reactionallreplies', $metadata ? ($metadata->reactionallreplies ? true : false) : false);

        $mform->addElement('checkbox', 'delayedcounter', get_string('reactions_delayedcounter', 'local_reactforum'));
        $mform->addHelpButton('delayedcounter', 'reactions_delayedcounter', 'local_reactforum');
        $mform->setDefault('delayedcounter', $metadata && $metadata->delayedcounter ? true : false);

        $mform->addElement('checkbox', 'changeable', get_string('reactions_changeable', 'local_reactforum'));
        $mform->addHelpButton('changeable', 'reactions_changeable', 'local_reactforum');
        $mform->setDefault('changeable', $metadata ? ($metadata->changeable ? true : false) : true);

        $mform->addElement('hidden', 'f');
        $mform->setDefault('f', $this->forumid);
        $mform->setType('f', PARAM_INT);
        $mform->addElement('hidden', 'd');
        $mform->setDefault('d', $this->discussionid);
        $mform->setType('d', PARAM_INT);

        $this->add_action_buttons(true);
    }

    /**
     * Updates or creates the metadata record for this forum/discussion.
     *
     * @return void
     */
    private function setmetadata(): void {
        global $DB;
        $data = $this->get_data();
        $metadata = local_reactforum_getreactionmetadata($this->forumid, $this->discussionid);
        if ($metadata) {
            $metadata->reactiontype = $data->reactiontype;
            $metadata->reactionallreplies = isset($data->reactionallreplies) ? $data->reactionallreplies : 0;
            $metadata->delayedcounter = isset($data->delayedcounter) ? $data->delayedcounter : 0;
            $metadata->changeable = isset($data->changeable) ? $data->changeable : 0;
            if (!$DB->update_record('reactforum_metadata', $metadata)) {
                throw new \core\exception\moodle_exception('error_invaliddiscussion', 'local_reactforum');
            }
            return;
        }
        $metadata = new \stdClass();
        $metadata->forum = $this->forumid;
        $metadata->discussion = $this->discussionid;
        $metadata->reactiontype = $data->reactiontype;
        $metadata->reactionallreplies = $data->reactionallreplies ?? 0;
        $metadata->delayedcounter = $data->delayedcounter ?? 0;
        $metadata->changeable = $data->changeable ?? 0;
        if (!$DB->insert_record('reactforum_metadata', $metadata)) {
            throw new \core\exception\moodle_exception('error_invaliddiscussion', 'local_reactforum');
        }
    }

    /**
     * Processes the submitted form data and updates the database.
     *
     * @param \context_module|null $modcontext
     * @return bool true on success
     */
    public function process($modcontext = null): bool {
        global $DB;
        if (!$modcontext) {
            $modcontext = local_reactforum_getmodcontextfromforumid($this->forumid);
        }
        $data = $this->get_data();

        $currentmetadata = local_reactforum_getreactionmetadata($this->forumid, $this->discussionid);

        if ($currentmetadata && $data->reactiontype !== $currentmetadata->reactiontype) {
            $reactions = $DB->get_records(
                'reactforum_buttons',
                ['forum' => $this->forumid, 'discussion' => $this->discussionid]
            );
            foreach ($reactions as $reaction) {
                local_reactforum_removereaction($reaction->id);
            }
            $DB->delete_records('reactforum_metadata', ['forum' => $this->forumid, 'discussion' => $this->discussionid]);

            if (!$this->discussionid) {
                $discussionreactions = $DB->get_records('reactforum_buttons', ['forum' => $this->forumid]);
                foreach ($discussionreactions as $discussionreaction) {
                    local_reactforum_removereaction($discussionreaction->id);
                }
                $DB->delete_records('reactforum_metadata', ['forum' => $this->forumid]);
            }
        }

        $this->setmetadata();

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
                $reaction->forum = $this->forumid;
                $reaction->discussion = $this->discussionid;
                $reaction->reaction = $reactiontxt;
                if (!$DB->insert_record('reactforum_buttons', $reaction)) {
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
                $reaction->forum = $this->forumid;
                $reaction->discussion = $this->discussionid;
                $reaction->reaction = $description;
                $reactionid = $DB->insert_record('reactforum_buttons', $reaction);
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
                $DB->update_record('reactforum_buttons', $reactionobj);
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
                $DB->update_record('reactforum_buttons', $reactionobj);
            }
        }

        // Delete reactions.
        $deletereactions = optional_param_array('reactions_delete', [], PARAM_INT);
        foreach ($deletereactions as $reactionid) {
            local_reactforum_removereaction($reactionid);
        }

        if ($data->reactiontype === 'none') {
            $reactions = $DB->get_records(
                'reactforum_buttons',
                ['forum' => $this->forumid, 'discussion' => $this->discussionid]
            );
            foreach ($reactions as $reaction) {
                local_reactforum_removereaction($reaction->id);
            }
            $DB->delete_records('reactforum_metadata', ['forum' => $this->forumid, 'discussion' => $this->discussionid]);
        }

        return true;
    }
}
