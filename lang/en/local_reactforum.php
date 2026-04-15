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
 * English language strings for local_reactforum.
 *
 * @package     local_reactforum
 * @category    string
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['description'] = 'Description';

$string['error_cannotreactownpost'] = 'Cannot react to your own post';
$string['error_draftnotfound'] = 'Draft file not found';
$string['error_invaliddiscussion'] = 'Invalid discussion ID';
$string['error_invalidforum'] = 'Invalid forum ID';
$string['error_invalidreaction'] = 'Invalid reaction ID';
$string['error_reactionnotchangeable'] = 'Reactions cannot be changed';
$string['error_repliesnotallowed'] = 'Reactions on replies are not enabled';

$string['eventreactioncreated'] = 'Reaction created';
$string['eventreactiondeleted'] = 'Reaction deleted';

$string['pluginname'] = 'Reactforum';

$string['privacy:metadata:local_reactforum_userreactions'] = 'Stores which reaction each user has applied to each post.';
$string['privacy:metadata:local_reactforum_userreactions:post'] = 'The ID of the post that was reacted to.';
$string['privacy:metadata:local_reactforum_userreactions:reaction'] = 'The ID of the reaction button used.';
$string['privacy:metadata:local_reactforum_userreactions:userid'] = 'The ID of the user who reacted.';

$string['reactforum:forumconfig'] = 'Configure reactions of a forum module';

$string['reactions'] = 'Reactions';
$string['reactions_add'] = 'Add';
$string['reactions_allreplies'] = 'Apply reaction buttons on replies';
$string['reactions_allreplies_help'] = 'If this option is checked, reaction buttons will appear on each topic and every reply as well. Otherwise, they appear on the discussion topic only.';
$string['reactions_cancel'] = 'Cancel';
$string['reactions_changeable'] = 'Allow changing reactions';
$string['reactions_changeable_help'] = 'If enabled, users can undo or change their reaction, otherwise they will be able to react only once per post.';
$string['reactions_changeimage'] = 'Change Image';
$string['reactions_delayedcounter'] = 'Delayed counter visibility';
$string['reactions_delayedcounter_help'] = 'If enabled, the counter on the buttons will not be displayed to the students until they click a button.';
$string['reactions_delete'] = 'Delete';
$string['reactions_delete_confirmation'] = 'Are you sure that you want to delete this reaction? All its data will be removed. (You can undo this action by not saving discussion edit)';
$string['reactions_reupload'] = 'Reupload';
$string['reactions_selectfile'] = 'Please select new reaction image file';
$string['reactionsbuttons'] = 'Reaction Buttons';
$string['reactionsettings'] = 'Reaction settings';
$string['reactionstype'] = 'Reaction Buttons Type';
$string['reactionstype_change_confirmation'] = 'All current reaction buttons will be removed. Are you sure that you want to change reaction type?';
$string['reactionstype_discussion'] = 'Decided by discussion owner';
$string['reactionstype_image'] = 'Image';
$string['reactionstype_none'] = 'None';
$string['reactionstype_text'] = 'Text';
