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
 * Class to manage an entity supported by API
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

class entity {
    
    protected $mainTable;       // Table name, to define in herited classes
   
    protected $id;                  
    protected $fields = [];     // fields and entities lists  
    protected $record = -1;     // Enregistrement de la base de données
    
    protected $otherTables = [];    // [ code => objRecord, .... ]
    protected $otherDef = [];    // [ code => [ table, id ], .... ]
    
    protected $error = "ok";
    protected $fieldsError = [];
    
    static protected $modules = null;  // [ moduleId => moduleName, .... ]
    static protected $sections = null;  // [ sectionId => number, .... ]
    
  
    protected function defineFields() {
        // Fields loading, to overload in all herited classes
        // using following methods

        
    }

    
    function addDirect($name, $value) {
        // Add fields from name and value
        if (! $value instanceof field and ! $value instanceof entities)
            $value = new value($value);
        $this->fields[$name] = $value;
        return $this->fields[$name];
    }
    
    function addFields(...$names) {
        // Add fields from this table
        // each name is dbname or alias:dbname
       
        foreach($names as $name) $this->addField($name);
    }
    
    function addField($name, $aliasTableName = null) {
        // name is dbname or alias:dbname
        if (strpos($name, ":")) {
            $name = explode(":", $name);
            $alias = $name[0];
            $name = $name[1];
        } else $alias = $name;
        if (is_null($aliasTableName)) {
            $field = new field($this->record(), $this->mainTable, $this->id, $name);
        } else {
            $field = new field($this->otherTables[$name], $this->otherDef[$aliasTableName][0], $this->otherDef[$aliasTableName][1], $name);
        }
        $this->fields[$alias] = $field;
        return $field;
    }
    
    function addTable($name, $table, $record, $fields = []) {
        // Add table's record to define more fields
        $idName = database::idName($table);
        $this->otherDef[$name] = [ $table, $record->$idName];
        $this->otherTables[$name] = $record;
        foreach($fields as $fieldName) $this->addField($fieldName, $name);
    }
    
    function linkTable($table, $join, $fields = []) {
        // get a record from an other table (the first one)
        // $table : real table in database
        // $join : way to find the other records,
        //              it can be a field (from this table to the linked table id)
        //              or [ $targetName => $thisName  ] to have links through specific fields
        if (is_array($join)) {
            foreach($join as $targetName=>$thisName) break;
        } else {
            $targetName = $join;
            $thisName = database::idName($this->mainTable);
        } 
        $record = database::get($table, $this->record($thisName), $targetName);
        if (empty($record)) return null;
        $idName = database::idName($table);
        $id = $record->$idName;
        foreach ($fields as $name) {
            if (strpos($name, ":")) {
                $name = explode(":", $name);
                $alias = $name[0];
                $name = $name[1];
            } else $alias = $name;
            $field = new field($record, $table, $id, $name);
            $this->fields[$alias] = $field;
        }
        return $record;
    }
        
    function addEntitiesFromTable($name, $entityName, $table, $join, $indexOn = null) {
        // Add entities directly from the table
        // $name : name of the attribute (field)
        // $entityName : name of entity (class for entity)
        //          or [ fieldNames, ... ] for a basic entity based on a few fields of the table         
        // $table : real table in database
        // $join : way to find the other records,
        //              it can be a field (from linked table to this table id)
        //              or [ $targetName => $thisName  ] to have links through specific fields
        // $indexOn : field on witch index the entities (by default, id of $table)
        
        if (is_null($indexOn)) $indexOn = database::idName($table);
        if (is_array($join)) {
            foreach($join as $targetName=>$thisName) break;
        } else {
            $targetName = $join;
            $thisName = database::idName($this->mainTable);
        }
        $values = database::getAll($table, $this->record($thisName), $targetName);
        if (is_array($entityName)) array_unshift($entityName, $table);
        $this->fields[$name] = new entities($values, $entityName, $indexOn);    
        return $this->fields[$name];
    }
    
    function get() {
        // Extract the final object
        $result = [];
        foreach($this->fields as $name => $obj) {
            $objResult = $obj->get();
            if ( ! empty($objResult) or $objResult === 0 or $objResult === "0" )
                    $result[$name] = $objResult;
        }
        return $result;
    }
    
    
    function __construct($idOrRecord, $mainTable = null, $fields = [], $infoFields = []) {
        // Load the datas an make entity
        // $idOrRecord is the id on the mainTable or the record
        // $mainTable is the mainTable (can be defined by the class itself)
        // $fields ar fields to add before calling $this->defineFields()
        // $infoFields are values to add at the beginning of fields
        if (is_array($idOrRecord)) $idOrRecord = (object) $idOrRecord;
        if (is_object($idOrRecord)) {
            $this->record = $idOrRecord;
            $this->id = $this->record(database::idName($this->mainTable));
        } else $this->id = $idOrRecord;
        if (!empty($mainTable)) $this->mainTable = $mainTable;
        foreach($infoFields as $name => $value) $this->addDirect($name, $value)->onlyGet();
        if (!empty($fields)) $this->addFields(...$fields);
        $this->defineFields();
    }
    
    static function moduleName($moduleId) {
        if (is_null(static::$modules)) static::makeModules();
        if (isset(static::$modules[$moduleId])) return static::$modules[$moduleId];
        else return "";        
    }
        
    function getModuleName($cmid) {
        // Give the module name for a course_module
        if (is_null(static::$modules)) static::makeModules();
        $cm = database::get("course_modules", $cmid);
        if (empty($cm)) return "";
        if (isset(static::$modules[$cm->module])) return static::$modules[$cm->module];
        else return "";  
    }
    
    protected static function makeModules() {
        static::$modules = [];
        foreach(database::getAll("modules")  as $record) {
            static::$modules[$record->id] = $record->name;
        }
    }
    
    
    protected function record($name = null) {
        // Get the curent record from database
        if ( ! is_object($this->record) and $this->record == -1) {
            $record = database::get($this->mainTable, $this->id);
            if (empty($record)) $record = new \stdClass();
            $this->record = $record;
        } else $record = $this->record;
        if (empty($name)) return $record;
        if (isset($record->$name)) return $record->$name;
        else return null;
    }

        protected function error($error = "error") {
        $this->error = $error;
        return false;
    }
    
    function update($data, $previous) {
        // Update the fields
        if (!is_object($data) and !is_array($data)) return $this->error("error");
        $ko = false;
        foreach ($data as $index => $value) {
            if (!isset($this->fields[$index])) {
                $this->fieldsError[$index] = "notfound";
                continue;
            }
            if (!empty($previous) and isset($previous->$index)) $thatPrevious = $previous->$index;
            else $thatPrevious = null;
            if ( ! $this->fields[$index]->update($value, $thatPrevious)) {
                $ko = true;
                $this->fieldsError[$index] = "partial";
            }
            
        }
        return !$ko;

    }

    function getErrors() {
        // Return all errors in the tree
        if ($this->error != "ok") return $this->error;
        if (empty($this->fieldsError)) return null;
        $result = [];
        foreach ($this->fieldsError as $index => $error) {
            if ($error == "partial") {
                $subResult = $this->fields[$index]->getErrors();
                if (!empty($subResult)) $result[$index] = $subResult;
            } else if ($error != "ok") $result[$index] = $error;
        }
        if (empty($result)) return null;
        else return $result;
    }
}
