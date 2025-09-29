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
 * Class to manage entity fields not directly connected to the DB.
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

/**
 * Represents a simple value field for an entity.
 */
class value {

    /**
     * The stored value.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Whether the field is for information only (not returned).
     *
     * @var bool
     */
    protected $only_info = false;

    /**
     * Whether the field is only allowed in GET API.
     *
     * @var bool
     */
    protected $only_get = false;

    /**
     * Whether the field should be checked specially.
     *
     * @var bool
     */
    protected $to_check = false;

    /**
     * Error status of the field.
     *
     * @var string
     */
    protected $error = 'ok';

    /**
     * Constructor.
     *
     * @param mixed $value Initial value.
     */
    public function __construct($value) {
        $this->value = $value;
    }

    /**
     * Mark the field as information only.
     *
     * @return self
     */
    public function only_info(): self {
        $this->only_info = true;
        return $this;
    }

    /**
     * Mark the field as only available for GET.
     *
     * @return self
     */
    public function only_get(): self {
        $this->only_get = true;
        return $this;
    }

    /**
     * Mark the field to be specially checked.
     *
     * @return self
     */
    public function to_check(): self {
        $this->to_check = true;
        return $this;
    }

    /**
     * Get the value for API output.
     *
     * @return mixed|null
     */
    public function get() {
        if ($this->only_info) {
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
     * Attempt to update the value (not allowed).
     *
     * @param mixed $new_value New value.
     * @param mixed $previous Previous value (optional).
     * @return void
     */
    public function update($new_value, $previous = ''): void {
        // Simple values cannot be updated.
        $this->error = 'notfound';
    }

    /**
     * Get errors for this value.
     *
     * @return string|null
     */
    public function get_errors(): ?string {
        if ($this->error !== 'ok') {
            return $this->error;
        }
        return null;
    }
}
