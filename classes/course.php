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
 * Class to check courses.
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

/**
 * Represents a Moodle course wrapper with checks for restrictions.
 */
class course {

    /** @var object|null Course record. */
    protected $record = null;

    /** @var int|string Course id or shortname used to load record. */
    protected $idorshortname;

    /**
     * Constructor.
     *
     * @param int|string $idorshortname Course id or shortname.
     */
    public function __construct($idorshortname) {
        $this->idorshortname = $idorshortname;

        if (is_numeric($idorshortname)) {
            $this->record = database::get("course", $idorshortname, "id");
        } else {
            $this->record = database::get("course", $idorshortname, "shortname");
        }
    }

    /**
     * Magic getter to access record properties.
     *
     * @param string $name Field name.
     * @return mixed|null
     */
    public function __get($name) {
        if (isset($this->record->$name)) {
            return $this->record->$name;
        } else {
            return null;
        }
    }

    /**
     * Return the course record.
     *
     * @return object|null
     */
    public function get() {
        return $this->record;
    }

    /**
     * Check if the course exists.
     *
     * @return bool
     */
    public function is(): bool {
        return !empty($this->record);
    }

    /**
     * Check if the course is allowed according to plugin config.
     *
     * @return bool
     */
    public function allowed(): bool {
        if (!$this->is()) {
            return false;
        }
        if (!$this->check_in_list("allowed_courses")) {
            return false;
        }
        if ($this->check_in_list("restricted_courses", false)) {
            return false;
        }
        return true;
    }

    /**
     * Check if the course is in a configured list.
     *
     * @param string $name Config setting name.
     * @param bool $emptyisyes Whether empty config means "allowed".
     * @return bool
     */
    protected function check_in_list(string $name, bool $emptyisyes = true): bool {
        if (empty($this->record)) {
            return false;
        }

        $config = get_config("local_ezglobe", $name);
        $config = str_replace(",", "\n", $config);

        $empty = true;
        foreach (explode("\n", $config) as $course) {
            $course = trim($course);
            if (empty($course)) {
                continue;
            }
            $empty = false;

            if (is_numeric($course) && (int)$course === (int)$this->record->id) {
                return true;
            }
            if (is_string($course) && $course === $this->record->shortname) {
                return true;
            }
        }

        if ($emptyisyes) {
            return $empty;
        } else {
            return !$empty;
        }
    }
}
