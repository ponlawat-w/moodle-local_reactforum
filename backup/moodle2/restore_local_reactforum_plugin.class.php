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
     * Returns the restore path elements for forum modules.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure(): array {
        return [
            new restore_path_element(
                $this->get_namefor('local_reactforum_settings'),
                $this->get_pathfor('/local_reactforum_settings')
            ),
            new restore_path_element(
                $this->get_namefor('local_reactforum_reactions'),
                $this->get_pathfor('/local_reactforum_settings/local_reactforum_reactions/local_reactforum_reaction')
            ),
            new restore_path_element(
                $this->get_namefor('local_reactforum_user_reactions'),
                $this->get_pathfor('/local_reactforum_user_reactions/local_reactforum_user_reactions')
            ),
        ];
    }

    /**
     * Restores a local_reactforum_settings record.
     *
     * @param array $data
     * @return void
     */
    public function process_local_reactforum_local_reactforum_settings(array $data): void {
        global $DB;
        $record = (object) $data;
        $record->forum = $this->get_task()->get_activityid();
        $record->discussion = $record->discussion
            ? $this->get_mappingid('forum_discussion', $record->discussion)
            : null;
        $newid = $DB->insert_record('local_reactforum_settings', $record);
        $this->set_mapping($this->get_namefor('local_reactforum_settings'), $data['id'], $newid);
    }

    /**
     * Restores a local_reactforum_reactions record and its associated image files.
     *
     * @param array $data
     * @return void
     */
    public function process_local_reactforum_local_reactforum_reactions(array $data): void {
        global $DB;
        $record = (object) $data;
        $record->forum = $this->get_task()->get_activityid();
        $record->discussion = $record->discussion
            ? $this->get_mappingid('forum_discussion', $record->discussion)
            : null;
        $newid = $DB->insert_record('local_reactforum_reactions', $record);
        $this->set_mapping($this->get_namefor('local_reactforum_reactions'), $data['id'], $newid, true);
    }

    /**
     * Restores a local_reactforum_user_reactions record.
     *
     * @param array $data
     * @return void
     */
    public function process_local_reactforum_local_reactforum_user_reactions(array $data): void {
        global $DB;
        $record = (object) $data;
        $record->userid = $this->get_mappingid('user', $record->userid);
        $record->post = $this->get_mappingid('forum_post', $record->post);
        $record->reaction = $this->get_mappingid($this->get_namefor('local_reactforum_reactions'), $record->reaction);
        if ($record->userid && $record->post && $record->reaction) {
            $DB->insert_record('local_reactforum_user_reactions', $record);
        }
    }

    /**
     * After restore: move reaction image files to the new module context.
     *
     * @return void
     */
    protected function after_execute_module(): void {
        $this->add_related_files('local_reactforum', 'reactions', $this->get_namefor('local_reactforum_reactions'));
    }
}
