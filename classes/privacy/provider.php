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
 * Privacy provider for local_reactforum.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reactforum\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — the plugin stores user reaction choices in local_reactforum_userreactions.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about the data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_reactforum_userreactions',
            [
                'userid' => 'privacy:metadata:local_reactforum_userreactions:userid',
                'post' => 'privacy:metadata:local_reactforum_userreactions:post',
                'reaction' => 'privacy:metadata:local_reactforum_userreactions:reaction',
            ],
            'privacy:metadata:local_reactforum_userreactions'
        );
        return $collection;
    }

    /**
     * Gets context IDs that contain data for the given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = <<<SQL
            SELECT ctx.id
              FROM {context} ctx
              JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
              JOIN {forum} f ON f.id = cm.instance AND cm.module = (
                       SELECT id FROM {modules} WHERE name = 'forum'
                   )
              JOIN {forum_discussions} d ON d.forum = f.id
              JOIN {forum_posts} p ON p.discussion = d.id
              JOIN {local_reactforum_userreactions} rr ON rr.post = p.id
             WHERE rr.userid = :userid
            SQL;
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Gets all users who have data in the given context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!($context instanceof \context_module)) {
            return;
        }
        $sql = <<<SQL
            SELECT rr.userid
              FROM {local_reactforum_userreactions} rr
              JOIN {forum_posts} p ON p.id = rr.post
              JOIN {forum_discussions} d ON d.id = p.discussion
              JOIN {forum} f ON f.id = d.forum
              JOIN {course_modules} cm ON cm.instance = f.id AND cm.id = :cmid
            SQL;
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Exports user data for the given approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        /** @var \moodle_database $DB */
        $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_module)) {
                continue;
            }
            $sql = <<<SQL
                SELECT rr.id, rr.post, rr.reaction, p.discussion, d.forum
                  FROM {local_reactforum_userreactions} rr
                  JOIN {forum_posts} p ON p.id = rr.post
                  JOIN {forum_discussions} d ON d.id = p.discussion
                  JOIN {forum} f ON f.id = d.forum
                  JOIN {course_modules} cm ON cm.instance = f.id AND cm.id = :cmid
                 WHERE rr.userid = :userid
                SQL;
            $records = $DB->get_records_sql($sql, ['cmid' => $context->instanceid, 'userid' => $userid]);
            if ($records) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_reactforum')],
                    (object) ['reactions' => array_values($records)]
                );
            }
        }
    }

    /**
     * Deletes all data for all users in the given context.
     *
     * @param \core\context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\core\context $context): void {
        global $DB;
        /** @var \moodle_database $DB */
        $DB;
        if (!($context instanceof \context_module)) {
            return;
        }
        $sql = <<<SQL
            DELETE FROM {local_reactforum_userreactions}
             WHERE post IN (
                   SELECT p.id
                     FROM {forum_posts} p
                     JOIN {forum_discussions} d ON d.id = p.discussion
                     JOIN {forum} f ON f.id = d.forum
                     JOIN {course_modules} cm ON cm.instance = f.id AND cm.id = :cmid
               )
            SQL;
        $DB->execute($sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Deletes all data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        /** @var \moodle_database $DB */
        $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_module)) {
                continue;
            }
            $sql = <<<SQL
                DELETE FROM {local_reactforum_userreactions}
                 WHERE userid = :userid
                   AND post IN (
                       SELECT p.id
                         FROM {forum_posts} p
                         JOIN {forum_discussions} d ON d.id = p.discussion
                         JOIN {forum} f ON f.id = d.forum
                         JOIN {course_modules} cm ON cm.instance = f.id AND cm.id = :cmid
                   )
                SQL;
            $DB->execute($sql, ['userid' => $userid, 'cmid' => $context->instanceid]);
        }
    }

    /**
     * Deletes data for multiple users within a single context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        /** @var \moodle_database $DB */
        $DB;
        $context = $userlist->get_context();
        if (!($context instanceof \context_module)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED, 'uid');
        $sql = <<<SQL
            DELETE FROM {local_reactforum_userreactions}
             WHERE userid {$insql}
               AND post IN (
                   SELECT p.id
                     FROM {forum_posts} p
                     JOIN {forum_discussions} d ON d.id = p.discussion
                     JOIN {forum} f ON f.id = d.forum
                     JOIN {course_modules} cm ON cm.instance = f.id AND cm.id = :cmid
               )
            SQL;
        $DB->execute($sql, array_merge($inparams, ['cmid' => $context->instanceid]));
    }
}
