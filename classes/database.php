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
 * Class to access to database (using DB API from Moodle)
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

class database {

    protected static $idNames = [];     // Tables where id name is not "id" : [ "tableName" => "idName" ]

    static function idName($table) {
         if (isset(static::$idNames[$table])) return static::$idNames[$table]; 
        else return "id"; 
    }
    
    
    static function get($table, $value, $name = null) {
        if (is_null($name)) $name = static::idName($table);
        return static::loadOne("SELECT * FROM {" . $table . "} WHERE  `$name` = :$name", [ "$name" => $value]);
        
    }
       
    static function getAll($table, $value = null, $name = null) {
        if (is_null($name)) $name = static::idName($table);
        if (is_null($value)) return static::loadMultiple("SELECT * FROM {" . $table . "}");
        else return static::loadMultiple("SELECT * FROM {" . $table . "} WHERE  `$name` = :$name", [ "$name" => $value]);
        
    }
    
    
    static function loadOne($sql, $param = []) {
        global $DB;
        try {
            $result = $DB->get_record_sql($sql, $param);
            if (empty($result)) return null;
            else return $result;
        } catch (\dml_exception $ex) {
            return null;
        } catch (Exception $ex) {
            return null;
        }
    }
    
    static function loadMultiple($sql, $param = []) {
        global $DB;
        try {
            $result = $DB->get_records_sql($sql, $param);
            return (array) $result;
        } catch (\dml_exception $ex) {
            return [];
        } catch (Exception $ex) {
            return [];
        }
    }
    
    static function update($table, $id, $fieldName, $newValue) {
        global $DB;
        $object = new \stdClass;
        $idName = static::idName($table);
        $object->$idName = $id;
        $object->$fieldName = $newValue;
        try {
            $result = $DB->update_record($table, $object);
            return $result;
        } catch (\coding_exception $e) {
            return false;
        } catch (\dml_write_exception $e) {
            return false;
        } catch (\dml_exception $ex) {
            return false;
        } catch (Exception $ex) {
            return false;
        } 
        
    }

    
}
