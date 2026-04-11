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
 * External function: upload_reaction_image
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reactforum\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

/**
 * Moves a draft file to the plugin temporary area and returns its stored file id.
 *
 * This replaces the legacy imageuploaded.php endpoint.
 */
class upload_reaction_image extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'draftitemid' => new external_value(PARAM_INT, 'Draft item id of the uploaded file'),
            'filename' => new external_value(PARAM_FILE, 'Filename of the uploaded file'),
        ]);
    }

    /**
     * Copies a draft file to the plugin's temp area and returns the stored_file id.
     *
     * @param int $draftitemid
     * @param string $filename
     * @return int stored_file id
     */
    public static function execute(int $draftitemid, string $filename): int {
        global $USER;

        ['draftitemid' => $draftitemid, 'filename' => $filename] = self::validate_parameters(
            self::execute_parameters(),
            ['draftitemid' => $draftitemid, 'filename' => $filename]
        );

        $usercontext = \context_user::instance($USER->id);
        self::validate_context($usercontext);

        $fs = get_file_storage();

        $draftfile = $fs->get_file(
            $usercontext->id,
            'user',
            'draft',
            $draftitemid,
            '/',
            $filename
        );

        if (!$draftfile || $draftfile->is_directory()) {
            throw new \core\exception\moodle_exception('error_invalidreaction', 'local_reactforum');
        }

        // Find a unique itemid in the temp area.
        $tempitemid = time();
        while ($fs->file_exists($usercontext->id, 'local_reactforum', 'temp', $tempitemid, '/', $filename)) {
            $tempitemid++;
        }

        $tempfileinfo = [
            'contextid' => $usercontext->id,
            'component' => 'local_reactforum',
            'filearea' => 'temp',
            'itemid' => $tempitemid,
            'filepath' => '/',
            'filename' => $filename,
        ];

        $tempfile = $fs->create_file_from_storedfile($tempfileinfo, $draftfile);

        return (int) $tempfile->get_id();
    }

    /**
     * Describes the return value.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_INT, 'Stored file id of the temporary reaction image');
    }
}
