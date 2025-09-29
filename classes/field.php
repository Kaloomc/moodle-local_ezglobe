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
 * Class to manage fields.
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

/**
 * Represents a field with validation, updates, and gradebook sync.
 */
class field {

    /** @var string Table name. */
    protected $table;

    /** @var string Field name. */
    protected $name;

    /** @var int Record ID. */
    protected $id;

    /** @var mixed Field value. */
    protected $value;

    /** @var bool Restrict to info mode. */
    protected $onlyinfo = false;

    /** @var bool Restrict to get mode. */
    protected $onlyget = false;

    /** @var bool Mark field as requiring checks. */
    protected $tocheck = false;

    /** @var bool Whether this field is synchronised with gradebook. */
    protected $gradebook = false;

    /** @var string Error status. */
    protected $error = "ok";

    /** @var bool Enable previous verification. */
    protected static $previousverification = false;

    /** @var bool Allow field length extension. */
    protected static $extend = false;

    /** @var bool Enable gradebook updates. */
    protected static $updategradebook = false;

    /**
     * Configure global features.
     *
     * @param bool $previousverification Enable previous verification.
     * @param bool $extend Enable extension of fields.
     * @param bool $updategradebook Enable gradebook sync.
     */
    public static function features($previousverification, $extend, $updategradebook): void {
        if (get_config("local_ezglobe", "previous") == 1 && $previousverification == 1) {
            static::$previousverification = true;
        } else {
            static::$previousverification = false;
        }

        if (dbinfos::can_extend() && $extend == 1) {
            static::$extend = true;
        } else {
            static::$extend = false;
        }

        if (get_config("local_ezglobe", "gradebook") == 1 && $updategradebook == 1) {
            static::$updategradebook = true;
        }
    }

    /**
     * Constructor.
     *
     * @param mixed $value Field value.
     * @param string|null $table Table name.
     * @param int|null $id Record ID.
     * @param string|null $name Field name.
     */
    public function __construct($value = null, ?string $table = null, ?int $id = null, ?string $name = null) {
        $this->table = $table;
        $this->id = $id;
        $this->name = $name;

        if (is_array($value) && isset($value[$name])) {
            $this->value = $value[$name];
        } else if (is_object($value) && isset($value->$name)) {
            $this->value = $value->$name;
        } else if (is_object($value) || is_array($value)) {
            $this->value = null;
        } else {
            $this->value = $value;
        }
    }

    /**
     * Restrict this field to info mode (not updatable).
     *
     * @return self
     */
    public function onlyinfo(): self {
        $this->onlyinfo = true;
        return $this;
    }

    /**
     * Restrict this field to get mode (read-only).
     *
     * @return self
     */
    public function onlyget(): self {
        $this->onlyget = true;
        return $this;
    }

    /**
     * Mark this field as requiring verification checks.
     *
     * @return self
     */
    public function tocheck(): self {
        $this->tocheck = true;
        return $this;
    }

    /**
     * Mark this field as linked to the gradebook.
     *
     * @return self
     */
    public function gradebook(): self {
        $this->gradebook = true;
        return $this;
    }

    /**
     * Get value for GET API.
     *
     * @return mixed|null
     */
    public function get() {
        if ($this->onlyinfo) {
            return null;
        }
        if ($this->value === 0 || $this->value === "0") {
            return 0;
        }
        if (empty($this->value)) {
            return null;
        }
        return $this->value;
    }

    /**
     * Set error state.
     *
     * @param string $error Error code.
     * @return bool Always false.
     */
    protected function seterror(string $error = "error"): bool {
        $this->error = $error;
        return false;
    }

    /**
     * Update field value.
     *
     * @param mixed $newvalue New value.
     * @param string $previous Previous value.
     * @return bool
     */
    public function update($newvalue, string $previous = ""): bool {
        if ($this->onlyget || $this->onlyinfo) {
            return $this->seterror("notfound");
        }

        if (empty($newvalue) || empty(trim((string) $newvalue)) ||
            empty($this->value) || empty(trim((string) $this->value))) {
            return $this->seterror("empty");
        }

        if (!$this->check_previous($previous)) {
            return $this->seterror("previous");
        }

        if (!$this->check_and_extend(mb_strlen($newvalue))) {
            return $this->seterror("toolong");
        }

        if (!database::update($this->table, $this->id, $this->name, $newvalue)) {
            return $this->seterror("error");
        }

        if ($this->gradebook && static::$updategradebook) {
            $this->update_gradebook($newvalue);
        }

        return $this->error === "ok";
    }

    /**
     * Get errors if any.
     *
     * @return string|null
     */
    public function geterrors(): ?string {
        if ($this->error !== "ok") {
            return $this->error;
        }
        return null;
    }

    /**
     * Check previous value.
     *
     * @param string $previous Previous value.
     * @return bool
     */
    protected function check_previous(string $previous): bool {
        if (!static::$previousverification) {
            return true;
        }
        if (empty($previous) || empty(trim($previous))) {
            return false;
        }
        return trim($previous) === trim((string) $this->value);
    }

    /**
     * Check and extend field size if allowed.
     *
     * @param int $len New length.
     * @param string|null $table Table name.
     * @param string|null $field Field name.
     * @return bool
     */
    protected function check_and_extend(int $len, ?string $table = null, ?string $field = null): bool {
        if (!dbinfos::can_technical_extend()) {
            return true;
        }
        $table = $table ?? $this->table;
        $field = $field ?? $this->name;

        if ($len <= dbinfos::get_field_size($table, $field)) {
            return true;
        }

        if (!static::$extend) {
            return false;
        }

        if ($this->name === "name") {
            dbinfos::adjust_field("tool_recyclebin_course", "name", $len);
        }
        if ($this->name === "fullname") {
            dbinfos::adjust_field("tool_recyclebin_category", "fullname", $len);
        }
        if ($this->name === "shortname") {
            dbinfos::adjust_field("tool_recyclebin_category", "shortname", $len);
        }

        return dbinfos::adjust_field($table, $field, $len);
    }

    /**
     * Update gradebook item when field changes.
     *
     * @param string $newvalue New value.
     * @return bool
     */
    protected function update_gradebook(string $newvalue): bool {
        // Search a gradebook item to update.
        $sql = "SELECT * FROM {grade_items} WHERE itemname = :name
                  AND itemmodule = :module AND iteminstance = :instance";
        $param = [
            "name" => $this->value,
            "module" => $this->table,
            "instance" => $this->id,
        ];

        $item = database::load_one($sql, $param);
        if (empty($item)) {
            return true;
        }

        $len = mb_strlen($newvalue);
        if (!$this->check_and_extend($len, "grade_items", "itemname") ||
            !$this->check_and_extend($len, "grade_items_history", "itemname")) {
            return $this->seterror("gradebookfailed");
        }

        if (!database::update("grade_items", $item->id, "itemname", $newvalue)) {
            return $this->seterror("gradebookfailed");
        }

        return true;
    }
}
