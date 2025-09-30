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
 * @package    local_ezxlate
 * @copyright  2025 CBCD EURL & Ezxlate
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate;

/**
 * Generic entity wrapper for API-managed Moodle objects.
 */
class entity {

    /** @var string Main database table name. */
    protected $maintable;

    /** @var int|string|null Identifier for this entity. */
    protected $id;

    /** @var array<string,field|entities> Fields attached to this entity. */
    protected $fields = [];

    /** @var object|int Database record or -1 if lazy loaded. */
    protected $record = -1;

    /** @var array<string,object> Extra related table records. */
    protected $othertables = [];

    /** @var array<string,array> Mapping of related tables. */
    protected $otherdef = [];

    /** @var string Entity error state. */
    protected $error = 'ok';

    /** @var array<string,string> Field-level errors. */
    protected $fieldserror = [];

    /** @var array<int,string>|null Cache of module names by id. */
    protected static $modules = null;

    /** @var array<int,int>|null Cache of section numbers by id. */
    protected static $sections = null;

    /**
 * Constructor.
 *
 * @param int|object|array $idorrecord Identifier or DB record.
 * @param string|null $maintable Override main table.
 * @param array $fields Initial fields to add.
 * @param array $infofields Additional fixed info fields.
 */
    public function __construct($idorrecord, $maintable = null, $fields = [], $infofields = []) {
        if (is_array($idorrecord)) {
            $idorrecord = (object) $idorrecord;
        }
        if (is_object($idorrecord)) {
            $this->record = $idorrecord;
            $this->id = $this->record(database::id_name($this->maintable));
        } else {
            $this->id = $idorrecord;
        }
        if (!empty($maintable)) {
            $this->main_table = $maintable;
        }
        foreach ($infofields as $name => $value) {
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
     * @param string|null $aliastablename Optional table alias.
     * @return field
     */
    public function add_field(string $name, ?string $aliastablename = null) {
        if (strpos($name, ':') !== false) {
            [$alias, $name] = explode(':', $name, 2);
        } else {
            $alias = $name;
        }

        if (is_null($aliastablename)) {
            $field = new field($this->record(), $this->maintable, $this->id, $name);
        } else {
            $field = new field(
                $this->othertables[$name],
                $this->otherdef[$aliastablename][0],
                $this->otherdef[$aliastablename][1],
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
        $idname = database::id_name($table);
        $this->otherdef[$name] = [$table, $record->$idname];
        $this->othertables[$name] = $record;
        foreach ($fields as $fieldname) {
            $this->add_field($fieldname, $name);
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
            $targetname = key($join);
            $thisname = $join[$targetname];
        } else {
            $targetname = $join;
            $thisname = database::id_name($this->maintable);
        }

        $record = database::get($table, $this->record($thisname), $targetname);
        if (empty($record)) {
            return null;
        }

        $idname = database::id_name($table);
        $id = $record->$idname;

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
     * @param string|array $entityname Entity class name or field list.
     * @param string $table Table name.
     * @param string|array $join Join condition.
     * @param string|null $indexon Index field.
     * @return entities
     */
    public function add_entities_from_table(
        string $name,
        $entityname,
        string $table,
        $join,
        ?string $indexon = null
    ) {
        if (is_null($indexon)) {
            $indexon = database::id_name($table);
        }

        if (is_array($join)) {
            $targetname = key($join);
            $thisname = $join[$targetname];
        } else {
            $targetname = $join;
            $thisname = database::id_name($this->maintable);
        }

        $values = database::get_all($table, $this->record($thisname), $targetname);
        if (is_array($entityname)) {
            array_unshift($entityname, $table);
        }

        $this->fields[$name] = new entities($values, $entityname, $indexon);
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
            $objresult = $obj->get();
            if (!empty($objresult) || $objresult === 0 || $objresult === '0') {
                $result[$name] = $objresult;
            }
        }
        return $result;
    }

    /**
     * Get module name by id.
     *
     * @param int $moduleid Module id.
     * @return string
     */
    public static function module_name(int $moduleid): string {
        if (is_null(static::$modules)) {
            static::make_modules();
        }
        return static::$modules[$moduleid] ?? '';
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
            $record = database::get($this->maintable, $this->id);
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
                $this->fieldserror[$index] = 'notfound';
                continue;
            }
            $previousvalue = $previous->$index ?? null;
            if (!$this->fields[$index]->update($value, $previousvalue)) {
                $ko = true;
                $this->fieldserror[$index] = 'partial';
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
        if (empty($this->fieldserror)) {
            return null;
        }

        $result = [];
        foreach ($this->fieldserror as $index => $error) {
            if ($error === 'partial') {
                $subresult = $this->fields[$index]->get_errors();
                if (!empty($subresult)) {
                    $result[$index] = $subresult;
                }
            } else if ($error !== 'ok') {
                $result[$index] = $error;
            }
        }
        return empty($result) ? null : $result;
    }
}
