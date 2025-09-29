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

    /** @var mixed The stored value. */
    protected $value;

    /** @var bool Whether the field is for information only (not returned). */
    protected $onlyinfo = false;

    /** @var bool Whether the field is only allowed in GET API. */
    protected $onlyget = false;

    /** @var bool Whether the field should be checked specially. */
    protected $tocheck = false;

    /** @var string Error status of the field. */
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
        $this->onlyinfo = true;
        return $this;
    }

    /**
     * Mark the field as only available for GET.
     *
     * @return self
     */
    public function only_get(): self {
        $this->onlyget = true;
        return $this;
    }

    /**
     * Mark the field to be specially checked.
     *
     * @return self
     */
    public function to_check(): self {
        $this->tocheck = true;
        return $this;
    }

    /**
     * Get the value for API output.
     *
     * @return mixed|null
     */
    public function get() {
        if ($this->onlyinfo) {
            return null;
        }
        if ($this->value === 0 || $this->value === '0') {
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
     * @param mixed $newvalue New value.
     * @param mixed $previous Previous value (optional).
     * @return void
     */
    public function update($newvalue, $previous = ''): void {
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
