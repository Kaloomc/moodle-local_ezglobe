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
 * Class to manage a generic entity supported by the API.
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

/**
 * Generic entity wrapper for API-managed Moodle objects.
 */
class entity {

    /** @var string Main database table name. */
    protected $main_table;

    /** @var int|string|null Identifier for this entity. */
    protected $id;

    /** @var array<string,field|entities> Fields attached to this entity. */
    protected $fields = [];

    /** @var object|int Database record or -1 if lazy loaded. */
    protected $record = -1;

    /** @var array<string,object> Extra related table records. */
    protected $other_tables = [];

    /** @var array<string,array> Mapping of related tables. */
    protected $other_def = [];

    /** @var string Entity error state. */
    protected $error = 'ok';

    /** @var array<string,string> Field-level errors. */
    protected $fields_error = [];

    /** @var array<int,string>|null Cache of module names by id. */
    protected static $modules = null;

    /** @var array<int,int>|null Cache of section numbers by id. */
    protected static $sections = null;

    /**
     * Constructor.
     *
     * @param int|object|array $id_or_record Identifier or DB record.
     * @param string|null $main_table Override main table.
     * @param array $fields Initial fields to add.
     * @param array $info_fields Additional fixed info fields.
     */
    public function __construct($id_or_record, $main_table = null, $fields = [], $info_fields = []) {
        if (is_array($id_or_record)) {
            $id_or_record = (object) $id_or_record;
        }
        if (is_object($id_or_record)) {
            $this->record = $id_or_record;
            $this->id = $this->record(database::id_name($this->main_table));
        } else {
            $this->id = $id_or_record;
        }
        if (!empty($main_table)) {
            $this->main_table = $main_table;
        }
        foreach ($info_fields as $name => $value) {
            $this->add_direct($name, $value)->only_get();
        }
        if (!empty($fields)) {
            $this->add_fields(...$fields);
        }
        $this->define_fields();
    }

    /**
     * Define the fields available for this entity.
     * To be overridden by child classes.
     *
     * @return void
     */
    protected function define_fields() {
        // No default implementation.
    }

    /**
     * Add a direct field value.
     *
     * @param string $name Field name.
     * @param mixed $value Value.
     * @return field|entities
     */
    public function add_direct(string $name, $value) {
        if (!$value instanceof field && !$value instanceof entities) {
            $value = new value($value);
        }
        $this->fields[$name] = $value;
        return $this->fields[$name];
    }

    /**
     * Add multiple fields from this table.
     *
     * @param string ...$names Field names.
     * @return void
     */
    public function add_fields(...$names) {
        foreach ($names as $name) {
            $this->add_field($name);
        }
    }

    /**
     * Add one field from this table.
     *
     * @param string $name Field name (or alias:dbname).
     * @param string|null $alias_table_name Optional table alias.
     * @return field
     */
    public function add_field(string $name, ?string $alias_table_name = null) {
        if (strpos($name, ':') !== false) {
            [$alias, $name] = explode(':', $name, 2);
        } else {
            $alias = $name;
        }

        if (is_null($alias_table_name)) {
            $field = new field($this->record(), $this->main_table, $this->id, $name);
        } else {
            $field = new field(
                $this->other_tables[$name],
                $this->other_def[$alias_table_name][0],
                $this->other_def[$alias_table_name][1],
                $name
            );
        }
        $this->fields[$alias] = $field;
        return $field;
    }

    /**
     * Add another table to this entity.
     *
     * @param string $name Alias name.
     * @param string $table Table name.
     * @param object $record DB record.
     * @param array $fields Fields to add.
     * @return void
     */
    public function add_table(string $name, string $table, $record, array $fields = []) {
        $id_name = database::id_name($table);
        $this->other_def[$name] = [$table, $record->$id_name];
        $this->other_tables[$name] = $record;
        foreach ($fields as $field_name) {
            $this->add_field($field_name, $name);
        }
    }

    /**
     * Link a table by foreign key.
     *
     * @param string $table Table name.
     * @param string|array $join Join condition.
     * @param array $fields Fields to add.
     * @return object|null
     */
    public function link_table(string $table, $join, array $fields = []) {
        if (is_array($join)) {
            $target_name = key($join);
            $this_name = $join[$target_name];
        } else {
            $target_name = $join;
            $this_name = database::id_name($this->main_table);
        }

        $record = database::get($table, $this->record($this_name), $target_name);
        if (empty($record)) {
            return null;
        }

        $id_name = database::id_name($table);
        $id = $record->$id_name;

        foreach ($fields as $name) {
            if (strpos($name, ':') !== false) {
                [$alias, $name] = explode(':', $name, 2);
            } else {
                $alias = $name;
            }
            $field = new field($record, $table, $id, $name);
            $this->fields[$alias] = $field;
        }
        return $record;
    }

    /**
     * Add sub-entities from a related table.
     *
     * @param string $name Field name.
     * @param string|array $entity_name Entity class name or field list.
     * @param string $table Table name.
     * @param string|array $join Join condition.
     * @param string|null $index_on Index field.
     * @return entities
     */
    public function add_entities_from_table(
        string $name,
        $entity_name,
        string $table,
        $join,
        ?string $index_on = null
    ) {
        if (is_null($index_on)) {
            $index_on = database::id_name($table);
        }

        if (is_array($join)) {
            $target_name = key($join);
            $this_name = $join[$target_name];
        } else {
            $target_name = $join;
            $this_name = database::id_name($this->main_table);
        }

        $values = database::get_all($table, $this->record($this_name), $target_name);
        if (is_array($entity_name)) {
            array_unshift($entity_name, $table);
        }

        $this->fields[$name] = new entities($values, $entity_name, $index_on);
        return $this->fields[$name];
    }

    /**
     * Get final data array.
     *
     * @return array
     */
    public function get(): array {
        $result = [];
        foreach ($this->fields as $name => $obj) {
            $obj_result = $obj->get();
            if (!empty($obj_result) || $obj_result === 0 || $obj_result === '0') {
                $result[$name] = $obj_result;
            }
        }
        return $result;
    }

    /**
     * Get module name by id.
     *
     * @param int $module_id Module id.
     * @return string
     */
    public static function module_name(int $module_id): string {
        if (is_null(static::$modules)) {
            static::make_modules();
        }
        return static::$modules[$module_id] ?? '';
    }

    /**
     * Get module name for a course module.
     *
     * @param int $cmid Course module id.
     * @return string
     */
    public function get_module_name(int $cmid): string {
        if (is_null(static::$modules)) {
            static::make_modules();
        }
        $cm = database::get('course_modules', $cmid);
        if (empty($cm)) {
            return '';
        }
        return static::$modules[$cm->module] ?? '';
    }

    /**
     * Build the module cache.
     *
     * @return void
     */
    protected static function make_modules() {
        static::$modules = [];
        foreach (database::get_all('modules') as $record) {
            static::$modules[$record->id] = $record->name;
        }
    }

    /**
     * Get the current record.
     *
     * @param string|null $name Field name.
     * @return mixed
     */
    protected function record(?string $name = null) {
        if (!is_object($this->record) && $this->record === -1) {
            $record = database::get($this->main_table, $this->id);
            if (empty($record)) {
                $record = new \stdClass();
            }
            $this->record = $record;
        } else {
            $record = $this->record;
        }

        if (empty($name)) {
            return $record;
        }
        return $record->$name ?? null;
    }

    /**
     * Set an error state.
     *
     * @param string $error Error message.
     * @return bool
     */
    protected function error(string $error = 'error'): bool {
        $this->error = $error;
        return false;
    }

    /**
     * Update entity fields.
     *
     * @param object|array $data Data.
     * @param object|null $previous Previous state.
     * @return bool
     */
    public function update($data, $previous): bool {
        if (!is_object($data) && !is_array($data)) {
            return $this->error('error');
        }

        $ko = false;
        foreach ($data as $index => $value) {
            if (!isset($this->fields[$index])) {
                $this->fields_error[$index] = 'notfound';
                continue;
            }
            $previous_value = $previous->$index ?? null;
            if (!$this->fields[$index]->update($value, $previous_value)) {
                $ko = true;
                $this->fields_error[$index] = 'partial';
            }
        }
        return !$ko;
    }

    /**
     * Get entity errors.
     *
     * @return array|string|null
     */
    public function get_errors() {
        if ($this->error !== 'ok') {
            return $this->error;
        }
        if (empty($this->fields_error)) {
            return null;
        }

        $result = [];
        foreach ($this->fields_error as $index => $error) {
            if ($error === 'partial') {
                $sub_result = $this->fields[$index]->get_errors();
                if (!empty($sub_result)) {
                    $result[$index] = $sub_result;
                }
            } else if ($error !== 'ok') {
                $result[$index] = $error;
            }
        }
        return empty($result) ? null : $result;
    }
}
