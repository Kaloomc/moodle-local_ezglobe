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
 * Class to manage database structure (including extending fields).
 *
 * @package    local_ezxlate
 * @copyright  2025 CBCD EURL & Ezxlate
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate;

/**
 * Utility class for managing Moodle DB schema extensions.
 */
class dbinfos {

    /** @var array $tables Tables and fields info (max length, false if missing). */
    protected static $tables = [];

    /** @var array $initial Initial field sizes before extension. */
    protected static $initial = [];

    /** @var bool|null $cantechnicalextend Whether DB engine supports field extension. */
    protected static $cantechnicalextend = null;

    /** @var bool|null $canextend Whether the plugin is allowed to extend fields. */
    protected static $canextend = null;

    /**
     * Whether extension is allowed.
     *
     * @return bool
     */
    public static function canextend(): bool {
        if (is_null(static::$canextend)) {
            if (get_config("local_ezxlate", "key") == 0) {
                static::$canextend = false;
            } else if (!static::cantechnicalextend()) {
                static::$canextend = false;
            } else {
                static::$canextend = true;
            }
        }
        return static::$canextend;
    }

    /**
     * Checks if the DB engine technically allows extension (MySQL only).
     *
     * @return bool
     */
    public static function cantechnicalextend(): bool {
        global $DB;

        if (!is_null(static::$cantechnicalextend)) {
            return static::$cantechnicalextend;
        }

        if (!in_array($DB->get_dbfamily(), ["mysql"])) {
            static::$cantechnicalextend = false;
            return false;
        }

        static::$cantechnicalextend = !empty(static::getcolumninfo("user", "id"));
        return static::$cantechnicalextend;
    }

    /**
     * Adjusts the size of a field if possible.
     *
     * @param string $table Table name.
     * @param string $field Field name.
     * @param int $len New required length.
     * @return bool
     */
    public static function adjustfield(string $table, string $field, int $len): bool {
        global $DB;

        if (!static::canextend()) {
            return false;
        }

        $size = static::getfieldsize($table, $field);
        if ($size >= 65535 || empty($size) || $size >= $len) {
            return false;
        }

        if (!isset(static::$initial[$table])) {
            static::$initial[$table] = [];
        }
        if (!isset(static::$initial[$table][$field])) {
            static::$initial[$table][$field] = $size;
        }

        $size = 255;
        while ($len > $size) {
            $size = $size * 2 + 1;
        }

        $dbman = $DB->get_manager();
        $dbtable = new \xmldb_table($table);
        $dbfield = new \xmldb_field($field, XMLDB_TYPE_CHAR, "$size");
        $dbman->change_field_precision($dbtable, $dbfield);

        static::getfieldsize($table, $field, true);
        return true;
    }

    /**
     * Returns the list of extended fields.
     *
     * @return array
     */
    public static function getextensions(): array {
        $result = [];
        foreach (static::$initial as $table => $fields) {
            $result[$table] = [];
            foreach ($fields as $name => $size) {
                $result[$table][$name] = [
                    "previousSize" => $size,
                    "newSize" => static::getfieldsize($table, $name),
                ];
            }
        }
        return $result;
    }

    /**
     * Returns DB column information.
     *
     * @param string $table Table name.
     * @param string $field Field name.
     * @return mixed
     */
    protected static function getcolumninfo(string $table, string $field) {
        global $CFG, $DB;

        $table = $CFG->prefix . $table;
        $sql = "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :dbname
                   AND TABLE_NAME = :tablename
                   AND COLUMN_NAME = :field";

        try {
            return $DB->get_record_sql($sql, [
                'dbname' => $CFG->dbname,
                'tablename' => $table,
                'field' => $field,
            ]);
        } catch (\dml_exception $ex) {
            return null;
        } catch (\Exception $ex) {
            return null;
        }
    }

    /**
     * Gets the current field size.
     *
     * @param string $table Table name.
     * @param string $field Field name.
     * @param bool $force Force reload.
     * @return mixed
     */
    public static function getfieldsize(string $table, string $field, bool $force = false) {
        if (!static::cantechnicalextend()) {
            return false;
        }

        if (!isset(static::$tables[$table])) {
            static::$tables[$table] = [];
        }

        if (!isset(static::$tables[$table][$field]) || $force) {
            $infos = static::getcolumninfo($table, $field);
            if (empty($infos)) {
                static::$tables[$table][$field] = false;
            } else {
                static::$tables[$table][$field] = $infos->character_maximum_length;
            }
        }

        return static::$tables[$table][$field];
    }

    /**
     * Loads table information into memory.
     *
     * @param string $table Table name.
     * @return bool True if valid Moodle table with content.
     */
    protected static function loadtable(string $table): bool {
        global $DB;

        if (isset(static::$tables[$table])) {
            return !empty(static::$tables[$table]);
        }

        $sql = "SELECT * FROM {" . $table . "} LIMIT 1";
        try {
            $result = $DB->get_record_sql($sql);
            if (empty($result)) {
                static::$tables[$table] = false;
                return false;
            }

            $fields = [];
            foreach ($result as $name => $value) {
                $fields[$name] = true;
            }

            static::$tables[$table] = $fields;
            return true;
        } catch (\dml_exception $ex) {
            static::$tables[$table] = false;
            return false;
        } catch (\Exception $ex) {
            static::$tables[$table] = false;
            return false;
        }
    }
}
