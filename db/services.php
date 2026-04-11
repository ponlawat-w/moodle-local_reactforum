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
 * External function definitions for local_reactforum.
 *
 * @package     local_reactforum
 * @category    webservice
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_reactforum_get_discussion_reactions' => [
        'classname' => \local_reactforum\external\get_discussion_reactions::class,
        'description' => 'Get reaction metadata, buttons, and per-post state for a discussion.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_reactforum_react' => [
        'classname' => \local_reactforum\external\react::class,
        'description' => 'Toggle a reaction on a forum post.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_reactforum_upload_reaction_image' => [
        'classname' => \local_reactforum\external\upload_reaction_image::class,
        'description' => 'Move a draft file to the plugin temporary area and return its stored file id.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
