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
 * Class to manage fields
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

class field {
    
    protected $table;       // table name
    protected $name;        // field name
    protected $id;          // record id
    protected $value;       // value
    protected $onlyInfo = false;
    protected $onlyGet = false;
    protected $toCheck = false;
    protected $gradebook = false;       // True is this field has to be synchronised with gradebook
    
    protected $error = "ok";
    
    
    // Parameters about updating
    static protected $previousVerification = false;
    static protected $extend = false;
    static protected $updateGradebook = false;
    
    
    static function features($previousVerification, $extend, $updateGradebook ) {
        if (get_config("local_ezglobe", "previous") == 1 and $previousVerification == 1) static::$previousVerification = true;
        else static::$previousVerification = false;
        if (dbinfos::canExtend() and $extend == 1) static::$extend = true;
        else static::$extend = false;
        if (get_config("local_ezglobe", "gradebook") == 1 and $updateGradebook == 1) static::$updateGradebook = true;
    }
    
    function __construct($value = null, $table = null, $id = null, $name = null) {
        $this->table = $table;
        $this->id = $id;
        $this->name = $name;

        if (is_array($value) and isset($value[$name])) $this->value = $value[$name];
        else if (is_object($value) and isset($value->$name)) $this->value = $value->$name;
        else if (is_object($value) or is_array($value)) $this->value = null;
        else $this->value = $value;
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
    
    function gradebook() {
        $this->gradebook = true;
        return $this;
    }
    
    
    function get() {
        // Return value of field if it's allowed for GET API
        if ($this->onlyInfo) return null;
        if ($this->value === 0 or $this->value === "0") return 0;
        if (empty($this->value)) return null;
        return $this->value;
        
    }
    
    protected function error($error = "error") {
        $this->error = $error;
        return false;
    }
    
    function update($newValue, $previous = "") {
        if ( $this->onlyGet or $this->onlyInfo) return $this->error("notfound");
        if ( empty($newValue) or empty(trim($newValue)) 
                or empty($this->value) or empty(trim($this->value))) return $this->error("empty");
        if ( ! $this->checkPrevious($previous)) return $this->error("previous");
        if ( ! $this->checkAndExtend(mb_strlen($newValue))) return $this->error("toolong");
        if (!database::update($this->table, $this->id, $this->name, $newValue)) return $this->error("error");
        if ($this->gradebook and static::$updateGradebook) $this->updateGradebook($newValue);
        return $this->error == "ok";
        
    }
    
    function getErrors() {
        // Return all errors in the tree
        if ($this->error != "ok") return $this->error;
    }
    
    protected function checkPrevious($previous) {
        if (!static::$previousVerification) return true;
        if (empty($previous) or empty(trim($previous))) return false;
        if ( trim($previous) != trim($this->value)) return false; 
        else return true;
    }
    
    protected function checkAndExtend($len, $table = null, $field = null) {
        if ( !dbinfos::canTechnicalExtend()) return true;
        if (empty($table)) $table = $this->table;
        if (empty($field)) $field = $this->name;
        if ($len <= dbinfos::getFieldSize($table, $field)) return true;
        if (!static::$extend) return false;
        if ($this->name == "name") dbinfos::adjustField("tool_recyclebin_course", "name", $len);
        if ($this->name == "fullname") dbinfos::adjustField("tool_recyclebin_category", "fullname", $len);
        if ($this->name == "shortname") dbinfos::adjustField("tool_recyclebin_category", "shortname", $len);
        return dbinfos::adjustField($table, $field, $len);
    }
    
    protected function updateGradebook($newValue) {
        // search a line to upgrade
        $sql = "SELECT * FROM {grade_items} WHERE itemname = :name "
                . "AND itemmodule = :module and iteminstance = :instance";
        $param = [
            "name" => $this->value,
            "module" => $this->table,
            "instance" => $this->id
        ];
        $item = database::loadOne($sql, $param);
        if (empty($item)) return true;
        $len = mb_strlen($newValue);
        if (!$this->checkAndExtend($len, "grade_items", "itemname" ) or !$this->checkAndExtend($len, "grade_items_history", "itemname" ))
                return $this->error("gradebookfailed");
        if (!database::update("grade_items", $item->id, "itemname", $newValue)) 
                return $this->error("gradebookfailed");
        else return true;    
    }
    
}
