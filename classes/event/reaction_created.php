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
 * Event reaction_created
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reactforum\event;

/**
 * Fired when a user reacts to a forum post.
 */
class reaction_created extends \core\event\base {
    /**
     * Set basic properties for the event.
     */
    protected function init(): void {
        $this->data['objecttable'] = 'local_reactforum_userreactions';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Get event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventreactioncreated', 'local_reactforum');
    }

    /**
     * Get event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' reacted to forum post with id '{$this->other['postid']}'.";
    }

    /**
     * Get event URL.
     *
     * @return \core\url
     */
    public function get_url(): \core\url {
        return new \core\url('/mod/forum/discuss.php', [
            'p' => $this->other['postid'],
        ]);
    }

    /**
     * Create an event instance from a local_reactforum_userreactions record id.
     *
     * @param int $reactedid id of the local_reactforum_userreactions record
     * @param int $postid id of the forum post
     * @param \core\context\module $context
     * @return reaction_created
     */
    public static function createfromreacted(int $reactedid, int $postid, \core\context\module $context): reaction_created {
        return static::create([
            'context' => $context,
            'objectid' => $reactedid,
            'other' => ['postid' => $postid],
        ]);
    }
}
