<?php

defined('MOODLE_INTERNAL') or die();

require_once(__DIR__ . '/../../../../lib/formslib.php');
require_once(__DIR__ . '/../../lib.php');

class reactionsettings_form extends \moodleform
{
    public $forumid = null;
    public $discussionid = null;

    public function __construct($forumid = null, $discussionid = null) {
        global $DB;
        $this->forumid = $forumid;
        $this->discussionid = $discussionid;
        if ($this->discussionid) {
            $discussion = $DB->get_record('forum_discussions', ['id' => $this->discussionid]);
            if (!$discussion) {
                throw new moodle_exception('Invalid discussion ID');
            }
            $this->forumid = $discussion->forum;
        }
        parent::__construct();
    }

    public function definition() {
        $mform = $this->_form;

        $metadata = local_reactforum_getreactionmetadata($this->forumid, $this->discussionid);

        $radioarray = [
            $mform->createElement('radio', 'reactiontype', '', get_string('reactionstype_none', 'local_reactforum'), 'none'),
            $mform->createElement('radio', 'reactiontype', '', get_string('reactionstype_text', 'local_reactforum'), 'text'),
            $mform->createElement('radio', 'reactiontype', '', get_string('reactionstype_image', 'local_reactforum'), 'image'),
        ];
        if (!$this->discussionid) {
            $radioarray[] = $mform->createElement('radio', 'reactiontype', '', get_string('reactionstype_discussion', 'local_reactforum'), 'discussion');
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

        $mform->addElement('hidden', 'f');
        $mform->setDefault('f', $this->forumid);
        $mform->setType('f', PARAM_INT);
        $mform->addElement('hidden', 'd');
        $mform->setDefault('d', $this->discussionid);
        $mform->setType('d', PARAM_INT);

        $this->add_action_buttons(true);
    }

    private function setmetadata() {
        global $DB;
        $data = $this->get_data();
        $metadata = local_reactforum_getreactionmetadata($this->forumid, $this->discussionid);
        if ($metadata) {
            $metadata->reactiontype = $data->reactiontype;
            $metadata->reactionallreplies = isset($data->reactionallreplies) ? $data->reactionallreplies : 0;
            $metadata->delayedcounter = isset($data->delayedcounter) ? $data->delayedcounter : 0;
            if (!$DB->update_record('reactforum_metadata', $metadata)) {
                throw new moodle_exception('Cannot update reaction type entry');
            }
            return;
        }
        $metadata = new stdClass();
        $metadata->forum = $this->forumid;
        $metadata->discussion = $this->discussionid;
        $metadata->reactiontype = $data->reactiontype;
        $metadata->reactionallreplies = $data->reactionallreplies ?? 0;
        $metadata->delayedcounter = $data->delayedcounter ?? 0;
        if (!$DB->insert_record('reactforum_metadata', $metadata)) {
            throw new moodle_exception('Cannot add reaction type entry');
        }
    }

    public function process($modcontext = null) {
        global $DB;
        if (!$modcontext) {
            $modcontext = local_reactforum_getmodcontextfromforumid($this->forumid);
        }
        $data = $this->get_data();

        $currentmetadata = local_reactforum_getreactionmetadata($this->forumid, $this->discussionid);

        if ($currentmetadata && $data->reactiontype != $currentmetadata->reactiontype) {
            $reactions = $DB->get_records('reactforum_buttons', ['forum' => $this->forumid, 'discussion' => $this->discussionid]);
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

        if (isset($_POST['reactions']) && isset($_POST['reactions']['new'])) {
            if ($data->reactiontype == 'text') {
                foreach ($_POST['reactions']['new'] as $reactiontxt) {
                    $reaction = new stdClass();
                    $reaction->forum = $this->forumid;
                    $reaction->discussion = $this->discussionid;
                    $reaction->reaction = $reactiontxt;
                    if (!$DB->insert_record('reactforum_buttons', $reaction)) {
                        throw new moodle_exception('Cannot create reaction');
                    }
                }
            } else if ($data->reactiontype == 'image') {
                foreach ($_POST['reactions']['new'] as $fileid) {
                    $description = isset($_POST['reactions']['desc'])
                        && isset($_POST['reactions']['desc']['new'])
                        && isset($_POST['reactions']['desc']['new'][$fileid])
                            ? $_POST['reactions']['desc']['new'][$fileid] : '';

                    $reaction = new stdClass();
                    $reaction->forum = $this->forumid;
                    $reaction->discussion = $this->discussionid;
                    $reaction->reaction = $description;
                    $reactionid = $DB->insert_record('reactforum_buttons', $reaction);
                    if (!$reactionid) {
                        throw new moodle_exception('Cannot create reaction');
                    }
                    if (!local_reactforum_savetemp($fs, $modcontext->id, $fs->get_file_by_id($fileid), $reactionid)) {
                        throw new moodle_exception('Cannot save reaction image from uploaded file');
                    }
                }
                local_reactforum_cleartemp($fs);
            }
        }
        if (isset($_POST['reactions']) && isset($_POST['reactions']['edit'])) {
            if ($data->reactiontype == 'text') {
                foreach ($_POST['reactions']['edit'] as $reactionid => $reaction) {
                    if (trim($reaction) == '') {
                        continue;
                    }
                    $reactionobj = new stdClass();
                    $reactionobj->id = $reactionid;
                    $reactionobj->reaction = $reaction;
                    $DB->update_record('reactforum_buttons', $reactionobj);
                }
            } else if ($data->reactiontype == 'image') {
                foreach ($_POST['reactions']['edit'] as $reactionid => $filetempid) {
                    if ($filetempid > 0) {
                        local_reactforum_savetemp($fs, $modcontext->id, $fs->get_file_by_id($filetempid), $reactionid);
                    }
                }
                if (isset($_POST['reactions']['desc']) && isset($_POST['reactions']['desc']['edit'])) {
                    foreach ($_POST['reactions']['desc']['edit'] as $reactionid => $newdescription) {
                        $reactionobj = new stdClass();
                        $reactionobj->id = $reactionid;
                        $reactionobj->reaction = $newdescription;
                        $DB->update_record('reactforum_buttons', $reactionobj);
                    }
                }
            }
        }
        if (isset($_POST['reactions']) && isset($_POST['reactions']['delete'])) {
            foreach ($_POST['reactions']['delete'] as $reactionid) {
                local_reactforum_removereaction($reactionid);
            }
        }
        if ($data->reactiontype == 'none') {
            $reactions = $DB->get_records('reactforum_buttons', ['forum' => $this->forumid, 'discussion' => $this->discussionid]);
            foreach ($reactions as $reaction) {
                local_reactforum_removereaction($reaction->id);
            }
            $DB->delete_records('reactforum_metadata', ['forum' => $this->forumid, 'discussion' => $this->discussionid]);
        }
        return true;
    }
}
