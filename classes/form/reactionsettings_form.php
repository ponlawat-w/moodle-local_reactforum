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
        /** @var \moodle_database $DB */
        $DB;
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

        $reactionsetting = local_reactforum_getreactionsetting($this->forumid, $this->discussionid);

        local_reactforum_applytoform($mform, $reactionsetting, !$this->discussionid);

        $mform->addElement('hidden', 'f');
        $mform->setDefault('f', $this->forumid);
        $mform->setType('f', PARAM_INT);
        $mform->addElement('hidden', 'd');
        $mform->setDefault('d', $this->discussionid);
        $mform->setType('d', PARAM_INT);

        $this->add_action_buttons(true);
    }

    /**
     * Processes the submitted form data and updates the database.
     *
     * @param \context_module|null $modcontext
     * @return bool true on success
     */
    public function process($modcontext = null): bool {
        return local_reactforum_processreactionsdata(
            $this->forumid,
            $this->discussionid,
            $this->get_data(),
            $modcontext
        );
    }
}
