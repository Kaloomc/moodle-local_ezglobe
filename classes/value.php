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
 * Class to manage entity fields not directly connected to DB
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

class value {
    
    protected $value;       // value
    protected $onlyInfo = false;
    protected $onlyGet = false;
    protected $toCheck = false;  
    
    protected $error = "ok";        // Ok if we don't try to update it

    
    function __construct($value) {
         $this->value = $value;
    }

    function onlyInfo() {
        $this->onlyInfo = true;
        return $this;
    }

    function onlyGet() {
        $this->onlyGet = true;
        return $this;
    }
    
    function toCheck() {
        $this->toCheck = true;
        return $this;
    }
    
    function get() {
        // Return value of field if it's allowed for GET API
        if ($this->onlyInfo) return null;
        if ($this->value === 0 or $this->value === "0") return 0;
        if (empty($this->value)) return null;
        return $this->value;
    }
    
    function update($newValue, $previous = "") {
        $this->error = "notfound";      // simple values can't be updated 
    }
    
    function getErrors() {
        // Return all errors in the tree
        if ($this->error != "ok") return $this->error;
    }
    
}
