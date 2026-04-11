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
        $metadata = new backup_nested_element(
            'reactforum_metadata',
            ['id'],
            ['discussion', 'reactiontype', 'reactionallreplies', 'delayedcounter', 'changeable']
        );

        // Reaction buttons (text labels or image descriptions).
        $buttons = new backup_nested_element('reactforum_buttons');
        $button = new backup_nested_element(
            'reactforum_button',
            ['id'],
            ['discussion', 'reaction']
        );

        // Individual user reactions on posts.
        $reacteds = new backup_nested_element('reactforum_reacteds');
        $reacted = new backup_nested_element(
            'reactforum_reacted',
            ['id'],
            ['userid', 'post', 'reaction']
        );

        // Build the element tree.
        $wrapper->add_child($metadata);
        $metadata->add_child($buttons);
        $buttons->add_child($button);

        $wrapper->add_child($reacteds);
        $reacteds->add_child($reacted);

        // Set sources — limit to this forum's data.
        $forumid = backup_helper::is_sqlparam($this->task->get_activityid());

        $metadata->set_source_sql(
            'SELECT * FROM {reactforum_metadata} WHERE forum = ?',
            [$forumid]
        );

        $button->set_source_sql(
            'SELECT * FROM {reactforum_buttons} WHERE forum = ?',
            [$forumid]
        );

        $reacted->set_source_sql(
            <<<SQL
            SELECT rr.*
              FROM {reactforum_reacted} rr
              JOIN {reactforum_buttons} rb ON rb.id = rr.reaction
             WHERE rb.forum = ?
            SQL,
            [$forumid]
        );

        // Annotate IDs that need remapping on restore.
        $reacted->annotate_ids('user', 'userid');
        $reacted->annotate_ids('forum_post', 'post');
        $reacted->annotate_ids('reactforum_button', 'reaction');

        // Annotate image files for image-type reactions.
        $button->annotate_files('local_reactforum', 'reactions', 'id');

        return $plugin;
    }
}
