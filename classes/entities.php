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
 * Class to manage entities lists
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

class entities {
    
    protected $list = [];       // [ $id => $entity ]
    protected $onlyInfo = false;
    protected $onlyGet = false;
    protected $toCheck = false;    
    
    protected $error = "ok";
    protected $entitiesError = [];

    
    function __construct($values, $entityName, $indexOn) {
        // $values : records from the main table
        // $entityName : name of entity (class for entity)
        //          or array with  main table name, then field names for a basic entity based on a few fields of the table   
        // $indexOn : field on witch index the entities
        
        if (is_array($entityName)) {
            $table = array_shift($entityName);
            $fields = $entityName;
            $entity = "\\local_ezglobe\\entity";
        } else {
            $entity = '\\local_ezglobe\\entities\\' . $entityName;
            $table = null;
            $fields = [];
        }
        foreach($values as $record) {
            $this->list[$record->$indexOn] = new $entity($record, $table, $fields);
        }

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
        if (empty($this->list)) return null;
        $result = [];
        foreach ($this->list as $index => $entity) {
            $value = $entity->get();
            if (!empty($value)) $result[$index] = $value;
        }
        if (empty($result)) return null;
        return $result;
    }
    
    protected function error($error = "error") {
        $this->error = $error;
        return false;
    }
    
    function update($data, $previous) {
        // Update the sub-entities
        if ($this->onlyInfo or $this->onlyGet) return $this->error("notfound");
        if (!is_object($data) and !is_array($data)) return $this->error("error");
        $ko = false;
        foreach ($data as $index => $entityData) {
            if (!isset($this->list[$index])) {
                $this->entitiesError[$index] = "notfound";
                $ko = true;
                continue;
            }
            if (isset($previous->$index)) $thatPrevious = $previous->$index;
            else $thatPrevious = new \stdClass();
            if ( ! $this->list[$index]->update($entityData, $thatPrevious)) {
                $ko = true;
                $this->entitiesError[$index] = "partial";
                $this->error = "partial";
            }
        }
        return !$ko;
    }
    
    function getErrors() {
        // Return all errors in the tree
        if ($this->error != "ok") return $this->error;
        if (empty($this->entitiesError)) return null;
        $result = [];
        foreach ($this->entitiesError as $index => $error) {
            if ($error == "partial") {
                $subResult = $this->list[$index]->getErrors();
                if (!empty($subResult)) $result[$index] = $subResult;
            } else if ($error != "ok") $result[$index] = $error;
        }
        if (empty($result)) return null;
        else return $result;
    }
    
}
