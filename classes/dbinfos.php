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
 * Class to manage database structure (including extendings fields)
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

class dbinfos {
    
    
    static protected $tables = [];      // Tables, fields in tables
                                        // Values are :
                                        //     number : maximum length
                                        //     false : field not found
    
    static protected $initial = [];    // As tables, but only for modified fields, with the initial length

    static protected $canTechnicalExtend = null; 
    static protected $canExtend = null; 
    
    
    static function canExtend() {
        global $CFG, $DB;
        
        if ( is_null(static::$canExtend)) {
            if (get_config("local_ezglobe", "key") == 0 ) static::$canExtend = false;
            else if ( ! static::canTechnicalExtend()) static::$canExtend = false;
            else static::$canExtend = true;
        } 
        
        return static::$canExtend;

    }
     
    static function canTechnicalExtend() {
        global $CFG, $DB;
        
        if ( ! is_null(static::$canTechnicalExtend)) return static::$canTechnicalExtend;
        if ( ! in_array($DB->get_dbfamily(), ["mysql"])) {
            static::$canTechnicalExtend = false;
            return false;
        }
        
        // Tentative d'accéder au schéma
        static::$canTechnicalExtend = ! empty(static::getColumnInfo("user", "id"));
        return static::$canTechnicalExtend;
    }
    
    static function adjustField($table, $field, $len) {
        global $DB;
        if (!static::canExtend()) return false;
        $size = static::getFieldSize($table, $field);
        if ($size >= 65535 or empty($size) or $size >= $len ) return false;

        if ( ! isset(static::$initial[$table])) static::$initial[$table] = [];
        if ( ! isset(static::$initial[$table][$field])) static::$initial[$table][$field] = $size;
        $size = 255;
        while( $len > $size) $size = $size * 2 + 1;
        $dbman = $DB->get_manager();
        $dbtable = new \xmldb_table($table); 
        $dbfield = new \xmldb_field($field, XMLDB_TYPE_CHAR, "$size");
        $dbman->change_field_precision($dbtable, $dbfield);
        static::getFieldSize($table, $field, true);
        return true;
    }
    
    static function getExtensions() {
        $result = [];
        foreach(static::$initial as $table => $fields) {
            $result[$table] = [];
            foreach($fields as $name => $size) {
                $result[$table][$name] = [ "previousSize" => $size, 
                            "newSize" =>  static::getFieldSize($table, $name)];
            }
        }
        return $result;
    }
    
    protected static function getColumnInfo($table, $field) {
        global $CFG, $DB;
        $table = $CFG->prefix . $table;
        $sql = "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = '$CFG->dbname'
            AND TABLE_NAME = '$table'
            AND COLUMN_NAME = '$field' ";
        try {
            $result = $DB->get_record_sql($sql);
            return $result;
        } catch (\dml_exception $ex) {
            return null;
        } catch (Exception $ex) {
            return new null;
        }
        
    }
    
    static function getFieldSize($table, $field, $force = false) {
        if (!static::canTechnicalExtend()) return false;
        if ( ! isset(static::$tables[$table])) static::$tables[$table] = [];
        if ( ! isset(static::$tables[$table][$field]) or $force) {
            $infos = static::getColumnInfo($table, $field);
            if (empty($infos)) static::$tables[$table][$field] = false;
            else static::$tables[$table][$field] = $infos->character_maximum_length;
        } 
        return static::$tables[$table][$field];
    }
    
    
    protected static function loadTable($table) {
        // Return : true is $table is a moodle table and not empty, false if not       
        global $CFG, $DB;
        if (isset(static::$tables[$table])) return !empty(static::$tables[$table]);
        
        $sql = "SELECT * FROM {" . $table  . "} LIMIT 1";
        try {
            $result = $DB->get_record_sql($sql);
            if (empty($result)) { 
                static::$tables[$table] = false;
                return false;
            }
            $fields = [];
            foreach($result as $name=>$value) $fields[$name] = true;
            static::$tables[$table] = $fields;
            return true;
        } catch (\dml_exception $ex) {
            static::$tables[$table] = false;
            return false;
        } catch (Exception $ex) {
            static::$tables[$table] = false;
            return false;
        }  
    }
    
}
