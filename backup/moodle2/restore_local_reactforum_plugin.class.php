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
 * Restore plugin for local_reactforum.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore plugin class for local_reactforum.
 */
class restore_local_reactforum_plugin extends restore_local_plugin {
    /**
     * @var array<int, int|null> Map of new local_reactforum_settings ID => old discussion ID (null if none).
     *                           forum and discussion are both resolved in after_restore_module().
     */
    private array $insertedsettingids = [];

    /**
     * @var array<int, array{oldid: int, olddiscussionid: int|null}> Map of new local_reactforum_reactions ID =>
     *      ['oldid' => old reaction ID, 'olddiscussionid' => old discussion ID or null].
     *      forum and discussion are both resolved in after_restore_module().
     */
    private array $insertedreactionids = [];

    /**
     * @var array<int, int> Map of new local_reactforum_userreactions ID => old post ID.
     *                      post is resolved in after_restore_module().
     */
    private array $inserteduserreactionids = [];

    /**
     * Returns the restore path elements for forum modules.
     *
     * The connection point is always 'module' for local plugins, matching the
     * backup's define_module_plugin_structure(). Data lives in module.xml.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure(): array {
        return [
            new restore_path_element(
                'setting',
                $this->get_pathfor('/reactionsettings')
            ),
            new restore_path_element(
                'reaction',
                $this->get_pathfor('/reactionsettings/reactions/reaction')
            ),
            new restore_path_element(
                'userreaction',
                $this->get_pathfor('/userreactions/userreaction')
            ),
        ];
    }

    /**
     * Restores a settings record.
     *
     * forum = 0 is used as a placeholder because the forum record has not been
     * inserted yet at this stage (module.xml is processed before forum.xml).
     * The correct forum ID is applied in after_restore_module().
     *
     * @param array $data
     * @return void
     */
    public function process_setting(array $data): void {
        global $DB;
        /** @var \moodle_database $DB */
        $DB;
        if ($this->task->get_modulename() !== 'forum') {
            return;
        }
        $record = (object) $data;
        $record->forum = 0; // Placeholder; corrected in after_restore_module().
        $olddiscussionid = $record->discussion ?: null;
        $record->discussion = null; // Placeholder; corrected in after_restore_module().
        $newid = $DB->insert_record('local_reactforum_settings', $record);
        $this->set_mapping('setting', $data['id'], $newid);
        $this->insertedsettingids[$newid] = $olddiscussionid;
    }

    /**
     * Restores a reactions record and its associated image files.
     *
     * Same forum placeholder approach as process_setting().
     *
     * @param array $data
     * @return void
     */
    public function process_reaction(array $data): void {
        global $DB;
        /** @var \moodle_database $DB */
        $DB;
        if ($this->task->get_modulename() !== 'forum') {
            return;
        }
        $record = (object) $data;
        $record->forum = 0; // Placeholder; corrected in after_restore_module().
        $olddiscussionid = $record->discussion ?: null;
        $record->discussion = null; // Placeholder; corrected in after_restore_module().
        $newid = $DB->insert_record('local_reactforum_reactions', $record);
        // Register the ID mapping now so process_userreaction can resolve it via get_mappingid().
        // Do NOT pass $restorefiles=true here: get_old_contextid() returns 0 at this stage
        // (process_activity hasn't run yet), which would store parentitemid=0 and break
        // the file-restore JOIN in send_files_to_pool. Files are wired up in after_restore_module().
        $this->set_mapping('reaction', $data['id'], $newid);
        $this->insertedreactionids[$newid] = ['oldid' => $data['id'], 'olddiscussionid' => $olddiscussionid];
    }

    /**
     * Restores a userreactions record.
     *
     * @param array $data
     * @return void
     */
    public function process_userreaction(array $data): void {
        global $DB;
        /** @var \moodle_database $DB */
        $DB;
        $record = (object) $data;
        $record->userid = $this->get_mappingid('user', $record->userid);
        $oldpostid = $record->post;
        $record->post = 0; // Placeholder; corrected in after_restore_module().
        $record->reaction = $this->get_mappingid('reaction', $record->reaction);
        $newid = $DB->insert_record('local_reactforum_userreactions', $record);
        $this->set_mapping('userreaction', $data['id'], $newid, true);
        $this->inserteduserreactionids[$newid] = $oldpostid;
    }

    /**
     * After restore: fix the forum foreign key and restore image files.
     *
     * By this point apply_activity_instance() has been called by the forum's own
     * restore step, so get_activityid() returns the correct new forum ID.
     *
     * @return void
     */
    protected function after_restore_module(): void {
        global $DB;
        /** @var \moodle_database $DB */
        $DB;

        $newforumid = $this->task->get_activityid();

        foreach ($this->insertedsettingids as $settingid => $olddiscussionid) {
            $DB->set_field('local_reactforum_settings', 'forum', $newforumid, ['id' => $settingid]);
            if (!$olddiscussionid) {
                continue;
            }
            $DB->set_field(
                'local_reactforum_settings',
                'discussion',
                $olddiscussionid ? $this->get_mappingid('forum_discussion', $olddiscussionid) : null,
                ['id' => $settingid],
            );
        }

        // By this point process_activity() has run, so get_old_contextid() is the real old context.
        $oldcontextid = $this->task->get_old_contextid();

        foreach ($this->insertedreactionids as $reactionid => $info) {
            $DB->set_field('local_reactforum_reactions', 'forum', $newforumid, ['id' => $reactionid]);
            if ($info['olddiscussionid']) {
                $DB->set_field(
                    'local_reactforum_reactions',
                    'discussion',
                    $this->get_mappingid('forum_discussion', $info['olddiscussionid']),
                    ['id' => $reactionid],
                );
            }
            // Re-register the mapping with $restorefiles=true and the now-correct old context ID,
            // so send_files_to_pool can match files via the parentitemid = f.contextid JOIN.
            $this->set_mapping('reaction', $info['oldid'], $reactionid, true, $oldcontextid);
        }

        foreach ($this->inserteduserreactionids as $userreactionid => $oldpostid) {
            $DB->set_field(
                'local_reactforum_userreactions',
                'post',
                $this->get_mappingid('forum_post', $oldpostid),
                ['id' => $userreactionid],
            );
        }

        $this->add_related_files('local_reactforum', 'reactions', 'reaction');
    }
}
