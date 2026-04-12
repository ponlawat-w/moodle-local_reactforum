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
 * Unit tests for local_reactforum.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reactforum;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

use advanced_testcase;
use context_module;

/**
 * Tests for local_reactforum functions and external services.
 *
 * @covers \local_reactforum
 */
final class local_reactforum_test extends advanced_testcase {
    /** @var \stdClass course record */
    private $course;
    /** @var \stdClass cm record */
    private $cm;
    /** @var \stdClass forum record */
    private $forum;
    /** @var context_module */
    private $context;
    /** @var \stdClass discussion record */
    private $discussion;
    /** @var \stdClass post record (first post) */
    private $post;
    /** @var \stdClass teacher user */
    private $teacher;
    /** @var \stdClass student user */
    private $student;

    /**
     * Sets up a minimal forum environment for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $this->course = $generator->create_course();
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->forum = $generator->create_module('forum', ['course' => $this->course->id]);
        $this->cm = get_coursemodule_from_instance('forum', $this->forum->id, $this->course->id);
        $this->context = context_module::instance($this->cm->id);

        $this->setUser($this->teacher);
        $this->discussion = $this->getDataGenerator()
            ->get_plugin_generator('mod_forum')
            ->create_discussion([
                'course' => $this->course->id,
                'forum' => $this->forum->id,
                'userid' => $this->teacher->id,
                'subject' => 'Test discussion',
                'message' => 'Test message',
            ]);
        global $DB;
        $this->post = $DB->get_record('forum_posts', ['discussion' => $this->discussion->id]);
    }

    /**
     * Creates a default forum-level settings record.
     *
     * @param string $reactiontype
     * @return \stdClass settings record
     */
    private function createreactionsetting(string $reactiontype = 'text'): \stdClass {
        global $DB;
        $reactionsetting = new \stdClass();
        $reactionsetting->forum = $this->forum->id;
        $reactionsetting->discussion = null;
        $reactionsetting->reactiontype = $reactiontype;
        $reactionsetting->reactionallreplies = 1;
        $reactionsetting->delayedcounter = 0;
        $reactionsetting->changeable = 1;
        $reactionsetting->id = $DB->insert_record('local_reactforum_settings', $reactionsetting);
        return $reactionsetting;
    }

    /**
     * Creates a reaction record.
     *
     * @param string $label
     * @param int|null $discussionid
     * @return \stdClass reaction record
     */
    private function createreaction(string $label = 'Like', $discussionid = null): \stdClass {
        global $DB;
        $reaction = new \stdClass();
        $reaction->forum = $this->forum->id;
        $reaction->discussion = $discussionid;
        $reaction->reaction = $label;
        $reaction->id = $DB->insert_record('local_reactforum_reactions', $reaction);
        return $reaction;
    }

    // Tests for local_reactforum_getreactionsetting.

    /**
     * Forum-level metadata is returned when no discussion override exists.
     *
     * @covers ::local_reactforum_getreactionsetting
     */
    public function test_getreactionmetadata_forum_level(): void {
        $this->createreactionsetting('text');
        $result = local_reactforum_getreactionsetting($this->forum->id);
        $this->assertNotEmpty($result);
        $this->assertEquals('text', $result->reactiontype);
        $this->assertNull($result->discussion);
    }

    /**
     * Discussion-level metadata overrides forum-level metadata.
     *
     * @covers ::local_reactforum_getreactionsetting
     */
    public function test_getreactionmetadata_discussion_level(): void {
        global $DB;
        $this->createreactionsetting('discussion');

        $discsetting = new \stdClass();
        $discsetting->forum = $this->forum->id;
        $discsetting->discussion = $this->discussion->id;
        $discsetting->reactiontype = 'text';
        $discsetting->reactionallreplies = 0;
        $discsetting->delayedcounter = 0;
        $discsetting->changeable = 1;
        $DB->insert_record('local_reactforum_settings', $discsetting);

        $result = local_reactforum_getreactionsetting($this->forum->id, $this->discussion->id);
        $this->assertNotEmpty($result);
        $this->assertEquals($this->discussion->id, $result->discussion);
    }

    // Tests for local_reactforum\external\react.

    /**
     * Reacting creates a DB record and fires reaction_created event.
     *
     * @covers \local_reactforum\external\react
     */
    public function test_react_creates_record_and_fires_event(): void {
        global $DB;
        $this->createreactionsetting();
        $reaction = $this->createreaction('Like');

        // React as the student on the teacher's post.
        $this->setUser($this->student);
        $eventsink = $this->redirectEvents();

        $result = \local_reactforum\external\react::execute($this->post->id, $reaction->id);

        $events = $eventsink->get_events();
        $eventsink->close();

        // Should have a reaction_created event.
        $createdevents = array_filter($events, fn($e) => $e instanceof \local_reactforum\event\reaction_created);
        $this->assertCount(1, $createdevents);

        // DB record should exist.
        $this->assertTrue($DB->record_exists('local_reactforum_user_reactions', [
            'post' => $this->post->id,
            'reaction' => $reaction->id,
            'userid' => $this->student->id,
        ]));

        // Result should have the reaction in it.
        $statebyid = [];
        foreach ($result as $state) {
            $statebyid[$state['reactionid']] = $state;
        }
        $this->assertArrayHasKey($reaction->id, $statebyid);
        $this->assertTrue($statebyid[$reaction->id]['reacted']);
    }

    /**
     * Clicking the same reaction button twice removes the reaction.
     *
     * @covers \local_reactforum\external\react
     */
    public function test_react_toggles_off_same_reaction(): void {
        global $DB;
        $this->createreactionsetting();
        $reaction = $this->createreaction('Like');

        $this->setUser($this->student);

        // React once.
        \local_reactforum\external\react::execute($this->post->id, $reaction->id);
        $this->assertEquals(1, $DB->count_records('local_reactforum_user_reactions', ['post' => $this->post->id]));

        // React again with same reaction — should toggle off.
        $eventsink = $this->redirectEvents();
        \local_reactforum\external\react::execute($this->post->id, $reaction->id);
        $events = $eventsink->get_events();
        $eventsink->close();

        $this->assertEquals(0, $DB->count_records('local_reactforum_user_reactions', ['post' => $this->post->id]));
        $deletedevents = array_filter($events, fn($e) => $e instanceof \local_reactforum\event\reaction_deleted);
        $this->assertCount(1, $deletedevents);
    }

    /**
     * Reaction can be changed when the changeable flag is set.
     *
     * @covers \local_reactforum\external\react
     */
    public function test_react_changes_reaction_when_changeable(): void {
        global $DB;
        $reactionsetting = $this->createreactionsetting();
        $reactionsetting->changeable = 1;
        $DB->update_record('local_reactforum_settings', $reactionsetting);

        $reaction1 = $this->createreaction('Like');
        $reaction2 = $this->createreaction('Dislike');

        $this->setUser($this->student);

        // React with reaction1.
        \local_reactforum\external\react::execute($this->post->id, $reaction1->id);

        // Change to reaction2.
        \local_reactforum\external\react::execute($this->post->id, $reaction2->id);

        $this->assertEquals(1, $DB->count_records('local_reactforum_user_reactions', ['post' => $this->post->id]));
        $this->assertTrue($DB->record_exists('local_reactforum_user_reactions', [
            'post' => $this->post->id,
            'reaction' => $reaction2->id,
        ]));
    }

    /**
     * Attempting to change reaction when not changeable throws an exception.
     *
     * @covers \local_reactforum\external\react
     */
    public function test_react_blocked_when_not_changeable(): void {
        global $DB;
        $reactionsetting = $this->createreactionsetting();
        $reactionsetting->changeable = 0;
        $DB->update_record('local_reactforum_settings', $reactionsetting);

        $reaction1 = $this->createreaction('Like');
        $reaction2 = $this->createreaction('Dislike');

        $this->setUser($this->student);

        // First reaction.
        \local_reactforum\external\react::execute($this->post->id, $reaction1->id);

        // Attempt to change should throw.
        $this->expectException(\moodle_exception::class);
        \local_reactforum\external\react::execute($this->post->id, $reaction2->id);
    }

    /**
     * Removing a reaction button deletes the button and all associated reacted records.
     *
     * @covers ::local_reactforum_removereaction
     */
    public function test_removereaction_deletes_reacted_records(): void {
        global $DB;
        $this->createreactionsetting();
        $reaction = $this->createreaction('Like');

        $this->setUser($this->student);
        \local_reactforum\external\react::execute($this->post->id, $reaction->id);
        $this->assertEquals(1, $DB->count_records('local_reactforum_user_reactions', ['reaction' => $reaction->id]));

        $this->setAdminUser();
        local_reactforum_removereaction($reaction->id);
        $this->assertEquals(0, $DB->count_records('local_reactforum_user_reactions', ['reaction' => $reaction->id]));
        $this->assertEquals(0, $DB->count_records('local_reactforum_reactions', ['id' => $reaction->id]));
    }

    // Tests for privacy API.

    /**
     * Privacy provider correctly exports and deletes user reaction data.
     *
     * @covers \local_reactforum\privacy\provider
     */
    public function test_privacy_export_and_delete(): void {
        global $DB;
        $this->createreactionsetting();
        $reaction = $this->createreaction('Like');

        $this->setUser($this->student);
        \local_reactforum\external\react::execute($this->post->id, $reaction->id);

        // Verify data exists.
        $this->assertTrue($DB->record_exists('local_reactforum_user_reactions', ['userid' => $this->student->id]));

        // Get contexts.
        $contextlist = \local_reactforum\privacy\provider::get_contexts_for_userid($this->student->id);
        $this->assertNotEmpty($contextlist->get_contextids());

        // Delete data.
        $approvedlist = new \core_privacy\local\request\approved_contextlist(
            $this->student,
            'local_reactforum',
            $contextlist->get_contextids()
        );
        \local_reactforum\privacy\provider::delete_data_for_user($approvedlist);
        $this->assertFalse($DB->record_exists('local_reactforum_user_reactions', ['userid' => $this->student->id]));
    }
}
