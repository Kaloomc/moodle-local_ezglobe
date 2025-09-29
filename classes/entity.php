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
    protected $mainTable;

    /** @var int|string|null Identifier for this entity. */
    protected $id;

    /** @var array<string,field|entities> Fields attached to this entity. */
    protected $fields = [];

    /** @var object|int Database record or -1 if lazy loaded. */
    protected $record = -1;

    /** @var array<string,object> Extra related table records. */
    protected $otherTables = [];

    /** @var array<string,array> Mapping of related tables. */
    protected $otherDef = [];

    /** @var string Entity error state. */
    protected $error = 'ok';

    /** @var array<string,string> Field-level errors. */
    protected $fieldsError = [];

    /** @var array<int,string>|null Cache of module names by id. */
    protected static $modules = null;

    /** @var array<int,int>|null Cache of section numbers by id. */
    protected static $sections = null;

    /**
     * Constructor.
     *
     * @param int|object|array $idOrRecord Identifier or DB record.
     * @param string|null $mainTable Override main table.
     * @param array $fields Initial fields to add.
     * @param array $infoFields Additional fixed info fields.
     */
    public function __construct($idOrRecord, $mainTable = null, $fields = [], $infoFields = []) {
        if (is_array($idOrRecord)) {
            $idOrRecord = (object) $idOrRecord;
        }
        if (is_object($idOrRecord)) {
            $this->record = $idOrRecord;
            $this->id = $this->record(database::id_name($this->mainTable));
        } else {
            $this->id = $idOrRecord;
        }
        if (!empty($mainTable)) {
            $this->mainTable = $mainTable;
        }
        foreach ($infoFields as $name => $value) {
            $this->addDirect($name, $value)->onlyget();
        }
        if (!empty($fields)) {
            $this->addFields(...$fields);
        }
        $this->defineFields();
    }

    /**
     * Define the fields available for this entity.
     * To be overridden by child classes.
     *
     * @return void
     */
    protected function defineFields() {
        // No default implementation.
    }

    /**
     * Add a direct field value.
     *
     * @param string $name Field name.
     * @param mixed $value Value.
     * @return field|entities
     */
    public function addDirect(string $name, $value) {
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
    public function addFields(...$names) {
        foreach ($names as $name) {
            $this->addField($name);
        }
    }

    /**
     * Add one field from this table.
     *
     * @param string $name Field name (or alias:dbname).
     * @param string|null $aliasTableName Optional table alias.
     * @return field
     */
    public function addField(string $name, ?string $aliasTableName = null) {
        if (strpos($name, ':') !== false) {
            [$alias, $name] = explode(':', $name, 2);
        } else {
            $alias = $name;
        }

        if (is_null($aliasTableName)) {
            $field = new field($this->record(), $this->mainTable, $this->id, $name);
        } else {
            $field = new field(
                $this->otherTables[$name],
                $this->otherDef[$aliasTableName][0],
                $this->otherDef[$aliasTableName][1],
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
    public function addTable(string $name, string $table, $record, array $fields = []) {
        $idName = database::id_name($table);
        $this->otherDef[$name] = [$table, $record->$idName];
        $this->otherTables[$name] = $record;
        foreach ($fields as $fieldName) {
            $this->addField($fieldName, $name);
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
    public function linkTable(string $table, $join, array $fields = []) {
        if (is_array($join)) {
            $targetName = key($join);
            $thisName = $join[$targetName];
        } else {
            $targetName = $join;
            $thisName = database::id_name($this->mainTable);
        }

        $record = database::get($table, $this->record($thisName), $targetName);
        if (empty($record)) {
            return null;
        }

        $idName = database::id_name($table);
        $id = $record->$idName;

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
     * @param string|array $entityName Entity class name or field list.
     * @param string $table Table name.
     * @param string|array $join Join condition.
     * @param string|null $indexOn Index field.
     * @return entities
     */
    public function addEntitiesFromTable(
        string $name,
        $entityName,
        string $table,
        $join,
        ?string $indexOn = null
    ) {
        if (is_null($indexOn)) {
            $indexOn = database::id_name($table);
        }

        if (is_array($join)) {
            $targetName = key($join);
            $thisName = $join[$targetName];
        } else {
            $targetName = $join;
            $thisName = database::id_name($this->mainTable);
        }

        $values = database::get_all($table, $this->record($thisName), $targetName);
        if (is_array($entityName)) {
            array_unshift($entityName, $table);
        }

        $this->fields[$name] = new entities($values, $entityName, $indexOn);
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
            $objResult = $obj->get();
            if (!empty($objResult) || $objResult === 0 || $objResult === '0') {
                $result[$name] = $objResult;
            }
        }
        return $result;
    }

    /**
     * Get module name by id.
     *
     * @param int $moduleId Module id.
     * @return string
     */
    public static function moduleName(int $moduleId): string {
        if (is_null(static::$modules)) {
            static::makeModules();
        }
        return static::$modules[$moduleId] ?? '';
    }

    /**
     * Get module name for a course module.
     *
     * @param int $cmid Course module id.
     * @return string
     */
    public function getModuleName(int $cmid): string {
        if (is_null(static::$modules)) {
            static::makeModules();
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
    protected static function makeModules() {
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
            $record = database::get($this->mainTable, $this->id);
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
                $this->fieldsError[$index] = 'notfound';
                continue;
            }
            $previousValue = $previous->$index ?? null;
            if (!$this->fields[$index]->update($value, $previousValue)) {
                $ko = true;
                $this->fieldsError[$index] = 'partial';
            }
        }
        return !$ko;
    }

    /**
     * Get entity errors.
     *
     * @return array|string|null
     */
    public function getErrors() {
        if ($this->error !== 'ok') {
            return $this->error;
        }
        if (empty($this->fieldsError)) {
            return null;
        }

        $result = [];
        foreach ($this->fieldsError as $index => $error) {
            if ($error === 'partial') {
                $subResult = $this->fields[$index]->getErrors();
                if (!empty($subResult)) {
                    $result[$index] = $subResult;
                }
            } else if ($error !== 'ok') {
                $result[$index] = $error;
            }
        }
        return empty($result) ? null : $result;
    }
}
