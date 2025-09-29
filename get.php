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
 * Provides API endpoint to extract contents for translation.
 * 
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



define('AJAX_SCRIPT', true);
define('WS_SERVER', false);
require('../../config.php');
require('locallib.php');

/*
ini_set('display_errors',1);
error_reporting(E_ALL);
*/

$user = get_admin();
\core\session\manager::set_user($user);

$parameters = local_ezglobe_get_parameters();
if ($parameters === false) {
    local_ezglobe_return( api::error(api::syntaxerror, "Syntax error") );
    exit;
}

$param = local_ezglobe_get_parameters();        // Directly exit if not correct.

$api = new \local_ezglobe\api_get($param);
local_ezglobe_return($api->process());
