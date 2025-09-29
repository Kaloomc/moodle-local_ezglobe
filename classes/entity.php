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
 * Generic entity wrapper for API-managed Moodle objects.
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
            $this->maintable = $maintable;
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
     * Set an error state.
     *
     * @param string $error Error message.
     * @return bool
     */
    protected function set_error(string $error = 'error'): bool {
        $this->error = $error;
        return false;
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
        return $this->fieldserror;
    }
}
