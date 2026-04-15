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
 * Backup plugin for local_reactforum.
 *
 * Hooks into the module backup step to include reaction settings and
 * user reaction data whenever a forum module is backed up.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup plugin class for local_reactforum.
 */
class backup_local_reactforum_plugin extends backup_local_plugin {
    /**
     * Returns the backup structure for forum modules.
     *
     * Called for every module backup; exits early for non-forum modules.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        if ($this->task->get_modulename() !== 'forum') {
            return;
        }

        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);

        // Reaction configuration per forum (and optionally per discussion).
        $setting = new backup_nested_element(
            'reactionsettings',
            ['id'],
            ['forum', 'discussion', 'reactiontype', 'reactionallreplies', 'delayedcounter', 'changeable']
        );

        // Reaction buttons (text labels or image descriptions).
        $reactions = new backup_nested_element('reactions');
        $reaction = new backup_nested_element(
            'reaction',
            ['id'],
            ['forum', 'discussion', 'reaction']
        );

        // Individual user reactions on posts.
        $userreactions = new backup_nested_element('userreactions');
        $userreaction = new backup_nested_element(
            'userreaction',
            ['id'],
            ['userid', 'post', 'reaction']
        );

        // Build the element tree.
        $wrapper->add_child($setting);
        $setting->add_child($reactions);
        $reactions->add_child($reaction);

        $wrapper->add_child($userreactions);
        $userreactions->add_child($userreaction);

        // Set sources — limit to this forum's data.
        $forumid = backup_helper::is_sqlparam($this->task->get_activityid());

        $setting->set_source_sql(
            'SELECT * FROM {local_reactforum_settings} WHERE forum = ?',
            [$forumid]
        );

        $reaction->set_source_sql(
            'SELECT * FROM {local_reactforum_reactions} WHERE forum = ?',
            [$forumid]
        );

        $userreaction->set_source_sql(
            <<<SQL
            SELECT rr.*
              FROM {local_reactforum_userreactions} rr
              JOIN {local_reactforum_reactions} rb ON rb.id = rr.reaction
            WHERE rb.forum = ?
            SQL,
            [$forumid]
        );

        // Annotate IDs that need remapping on restore.
        $userreaction->annotate_ids('user', 'userid');
        $userreaction->annotate_ids('forum_post', 'post');
        $userreaction->annotate_ids('reaction', 'reaction');

        // Annotate image files for image-type reactions.
        $reaction->annotate_files('local_reactforum', 'reactions', 'id');

        return $plugin;
    }
}
