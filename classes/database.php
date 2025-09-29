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
 * Database helper class for EzGlobe local plugin.
 *
 * Provides a thin wrapper over Moodle DB API for common operations.
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

/**
 * Helper to access Moodle database.
 */
class database {

    /**
     * Cache of table id column names when not "id".
     *
     * @var array<string,string>
     */
    protected static $id_names = [];

    /**
     * Get the id field name for a table.
     *
     * @param string $table Table name.
     * @return string
     */
    public static function id_name(string $table): string {
        if (isset(static::$id_names[$table])) {
            return static::$id_names[$table];
        }
        return 'id';
    }

    /**
     * Get a single record from a table.
     *
     * @param string $table Table name.
     * @param mixed $value Value to match.
     * @param string|null $name Field name (default id field).
     * @return object|null
     */
    public static function get(string $table, $value, ?string $name = null) {
        if (is_null($name)) {
            $name = static::id_name($table);
        }
        return static::load_one("SELECT * FROM {{$table}} WHERE `$name` = :$name", [$name => $value]);
    }

    /**
     * Get multiple records from a table.
     *
     * @param string $table Table name.
     * @param mixed|null $value Value to match (optional).
     * @param string|null $name Field name (default id field).
     * @return array<int,object>
     */
    public static function get_all(string $table, $value = null, ?string $name = null): array {
        if (is_null($name)) {
            $name = static::id_name($table);
        }
        if (is_null($value)) {
            return static::load_multiple("SELECT * FROM {{$table}}");
        }
        return static::load_multiple("SELECT * FROM {{$table}} WHERE `$name` = :$name", [$name => $value]);
    }

    /**
     * Load one record using SQL.
     *
     * @param string $sql SQL query.
     * @param array $param Parameters.
     * @return object|null
     */
    public static function load_one(string $sql, array $param = []) {
        global $DB;
        try {
            $result = $DB->get_record_sql($sql, $param);
            if (empty($result)) {
                return null;
            }
            return $result;
        } catch (\dml_exception $ex) {
            return null;
        } catch (\Exception $ex) {
            return null;
        }
    }

    /**
     * Load multiple records using SQL.
     *
     * @param string $sql SQL query.
     * @param array $param Parameters.
     * @return array<int,object>
     */
    public static function load_multiple(string $sql, array $param = []): array {
        global $DB;
        try {
            $result = $DB->get_records_sql($sql, $param);
            return (array) $result;
        } catch (\dml_exception $ex) {
            return [];
        } catch (\Exception $ex) {
            return [];
        }
    }

    /**
     * Update one field in a record.
     *
     * @param string $table Table name.
     * @param int $id Record id.
     * @param string $field_name Field name.
     * @param mixed $new_value New value.
     * @return bool Success.
     */
    public static function update(string $table, int $id, string $field_name, $new_value): bool {
        global $DB;

        $object = new \stdClass();
        $id_name = static::id_name($table);
        $object->$id_name = $id;
        $object->$field_name = $new_value;

        try {
            $DB->update_record($table, $object);
            return true;
        } catch (\coding_exception $e) {
            return false;
        } catch (\dml_write_exception $e) {
            return false;
        } catch (\dml_exception $ex) {
            return false;
        } catch (\Exception $ex) {
            return false;
        }
    }
}
