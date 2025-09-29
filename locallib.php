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

defined('MOODLE_INTERNAL') || die();

function local_ezglobe_return($answer) {            
    ////ob_get_clean();
    ////header('content-type:application/json');
    if (empty($answer)) $answer = [ "code" => "ko"];
    else if (is_string($answer)) $answer = [ "code" => "ko", "msg" => $answer];
    else if (! is_array($answer) and ! is_object($answer)) $answer = [ "code" => "ko"];
    if (is_array($answer) and empty($answer["code"])) $answer["code"] == "ok";
    if (is_object($answer) and empty($answer->code)) $answer->code == "ok";
    echo json_encode($answer);
}

function local_ezglobe_error($code = "error", $message = "") {            
    ////ob_get_clean();
    ///header('content-type:application/json');
    $answer = [ "code" => $code ];
    if ( !empty($message)) $answer["message"] == $message;
    echo json_encode($answer);
}

function local_ezglobe_get_parameters($alwaysReturn = false) {
    // Get parameters (json) from php://input
    // Exit if not correct if not $alwaysReturn
    $request = file_get_contents("php://input");
    $request = json_decode($request);

    if ($request === false and ! alwaysReturn) {
        local_ezglobe_error( "error", "Parameters format is incorrect");
        exit;
    }  
    return $request;
}

