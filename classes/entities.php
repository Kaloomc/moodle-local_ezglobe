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
 * Class to manage entities lists.
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

/**
 * Wrapper for handling multiple entities and their errors.
 */
class entities {

    /** @var array List of entities indexed by ID. */
    protected $list = [];

    /** @var bool Flag to restrict output to info only. */
    protected $onlyinfo = false;

    /** @var bool Flag to restrict output to "get" only. */
    protected $onlyget = false;

    /** @var bool Flag for entities that must be checked. */
    protected $tocheck = false;

    /** @var string General error status ("ok", "error", "partial"). */
    protected $error = "ok";

    /** @var array List of entity-specific errors. */
    protected $entitieserror = [];

    /**
     * Constructor for entities collection.
     *
     * @param array $values Records from the main table.
     * @param mixed $entityname Entity class name or array (table + fields).
     * @param string $indexon Field to index the entities on.
     */
    public function __construct($values, $entityname, string $indexon) {
        if (is_array($entityname)) {
            $table = array_shift($entityname);
            $fields = $entityname;
            $entity = "\\local_ezglobe\\entity";
        } else {
            $entity = '\\local_ezglobe\\entities\\' . $entityname;
            $table = null;
            $fields = [];
        }

        foreach ($values as $record) {
            $this->list[$record->$indexon] = new $entity($record, $table, $fields);
        }
    }

    /**
     * Set flag to "only info".
     *
     * @return self
     */
    public function onlyinfo(): self {
        $this->onlyinfo = true;
        return $this;
    }

    /**
     * Set flag to "only get".
     *
     * @return self
     */
    public function onlyget(): self {
        $this->onlyget = true;
        return $this;
    }

    /**
     * Set flag to "to check".
     *
     * @return self
     */
    public function tocheck(): self {
        $this->tocheck = true;
        return $this;
    }

    /**
     * Retrieve entities values for GET API.
     *
     * @return array|null
     */
    public function get(): ?array {
        if ($this->onlyinfo) {
            return null;
        }

        if (empty($this->list)) {
            return null;
        }

        $result = [];
        foreach ($this->list as $index => $entity) {
            $value = $entity->get();
            if (!empty($value)) {
                $result[$index] = $value;
            }
        }

        if (empty($result)) {
            return null;
        }
        return $result;
    }

    /**
     * Set an error state.
     *
     * @param string $error Error message.
     * @return bool Always false.
     */
    protected function seterror(string $error = "error"): bool {
        $this->error = $error;
        return false;
    }

    /**
     * Update sub-entities.
     *
     * @param object|array $data New entity data.
     * @param object $previous Previous data for comparison.
     * @return bool
     */
    public function update($data, $previous): bool {
        if ($this->onlyinfo || $this->onlyget) {
            return $this->seterror("notfound");
        }

        if (!is_object($data) && !is_array($data)) {
            return $this->seterror("error");
        }

        $ko = false;
        foreach ($data as $index => $entitydata) {
            if (!isset($this->list[$index])) {
                $this->entitieserror[$index] = "notfound";
                $ko = true;
                continue;
            }

            $thatprevious = $previous->$index ?? new \stdClass();

            if (!$this->list[$index]->update($entitydata, $thatprevious)) {
                $ko = true;
                $this->entitieserror[$index] = "partial";
                $this->error = "partial";
            }
        }
        return !$ko;
    }

    /**
     * Retrieve errors for all entities.
     *
     * @return array|string|null
     */
    public function geterrors() {
        if ($this->error !== "ok") {
            return $this->error;
        }

        if (empty($this->entitieserror)) {
            return null;
        }

        $result = [];
        foreach ($this->entitieserror as $index => $error) {
            if ($error === "partial") {
                $subresult = $this->list[$index]->geterrors();
                if (!empty($subresult)) {
                    $result[$index] = $subresult;
                }
            } else if ($error !== "ok") {
                $result[$index] = $error;
            }
        }

        if (empty($result)) {
            return null;
        }
        return $result;
    }
}
