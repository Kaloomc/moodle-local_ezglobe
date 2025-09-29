<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Utility functions for the Ezglobe local plugin.
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Outputs a JSON-encoded response to the client.
 *
 * @param mixed $answer Response data (string, array, object or null).
 * @return void
 */
function local_ezglobe_return($answer) {
    // Clear any buffered output (optional).
    // ob_get_clean();.

    // Set content type to JSON (optional).
    // header('Content-Type: application/json');.

    if (empty($answer)) {
        $answer = ['code' => 'ko'];
    } else if (is_string($answer)) {
        $answer = ['code' => 'ko', 'msg' => $answer];
    } else if (!is_array($answer) && !is_object($answer)) {
        $answer = ['code' => 'ko'];
    }

    if (is_array($answer) && empty($answer['code'])) {
        $answer['code'] = 'ok';
    }

    if (is_object($answer) && empty($answer->code)) {
        $answer->code = 'ok';
    }

    echo json_encode($answer);
}

/**
 * Outputs a JSON-encoded error response.
 *
 * @param string $code Error code.
 * @param string $message Optional error message.
 * @return void
 */
function local_ezglobe_error($code = 'error', $message = '') {
    // Clear any buffered output (optional).
    // ob_get_clean();.

    // Set content type to JSON (optional).
    // header('Content-Type: application/json');.

    $answer = ['code' => $code];

    if (!empty($message)) {
        $answer['message'] = $message;
    }

    echo json_encode($answer);
}

/**
 * Retrieves parameters from php://input as JSON.
 *
 * @param bool $alwaysreturn If true, returns request even if invalid.
 * @return mixed The decoded request object or false.
 */
function local_ezglobe_get_parameters($alwaysreturn = false) {
    // Get parameters (json) from php://input.
    $request = file_get_contents('php://input');
    $request = json_decode($request);

    if ($request === false && !$alwaysreturn) {
        local_ezglobe_error('error', 'Parameters format is incorrect');
        exit;
    }

    return $request;
}
