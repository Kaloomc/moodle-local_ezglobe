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
 * Generic class to manage api
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;
use \DateTime;
use \stdClass;

class api {
    
    
    protected $mode = "";
    protected $param = null;
    
    protected $data = null;     // To build the datas
    protected $answer = null;   // To build the main answer
    
        
    static function failed($code = "error", $message = "") {
        $answer = new \stdClass();
        $answer->code = $code;
        if ( !empty($message)) $answer->message = $message;
        return $answer;
    }
    
    function process() {
        // Process the request, and return the final message (object)
        $this->data = new \stdClass();
        $this->answer = new \stdClass();
        $this->answer->code = "ok";
        if ( !empty($message = $this->checkAuthentification())) return $this->error("auth", $message);
        $answer = $this->checkParameters();
        if (!empty($answer)) return $answer;
        
        $answer = $this->do();
        if (empty($answer)) $answer = $this->answer;
        if (empty($answer->data) and !empty((array) $this->data)) $answer->data = $this->data;
        return $answer;
        
    }
    
    protected function checkParameters() {
        // Analyse parameters
        // Return error message or null
        // Should be overloaded
        return null;
    }
    
    protected function do() {
        // Process the precise action : prepare the informations
        // Return a std object with code and other properties
        // Should be overloaded
        return null;
    }
    
    
    function error($code = "error", $message = "") {
        $answer = new \stdClass();
        $answer->code = $code;
        if ( !empty($message)) $answer->message = $message;
        return $answer;
    }

    protected function checkAuthentification() {
        // Return error message or ""
        if ( get_config("local_ezglobe", "open") != 1 ) return "API disabled";
        if ( empty(get_config("local_ezglobe", "key"))) return "Empty key, API disabled";
        if ( strlen(get_config("local_ezglobe", "key")) < 10 ) return "Key is too short, API disabled";
        if ( empty($this->param->key)) return "Key not provided in the request";
        if ( $this->param->key != get_config("local_ezglobe", "key")) return "Authentification failed"; 
        if ( $this->iprestricted() ) return "Your IP address " . strtolower(trim($_SERVER["REMOTE_ADDR"])) . " is not allowed";
        return "";
    }
    
    protected function iprestricted() {
        // return true if IP is restricted
        $ips = [];
        foreach( explode("\n", str_replace(",", "\n", get_config("local_ezglobe", "ips"))) as $ip) {
            $ip = strtolower(trim($ip));
            if (!empty($ip)) $ips[] = $ip;
        }
        if (empty($ips)) return false;
        
        $myIp = strtolower(trim($_SERVER["REMOTE_ADDR"]));
        foreach ($ips as $ip) {
            if ($myIp == $ip) return false;
        }
        return true;
    }
    
       
    function version() {
        global $CFG;
        $pluginpath = $CFG->dirroot . '/local/ezglobe/version.php';
        if (file_exists($pluginpath)) {
            $plugin = new \stdClass();
            include($pluginpath);
            return $plugin->version;
        } else {
            return null;
        }

    }
    

}
