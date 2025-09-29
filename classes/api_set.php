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
 * API handler for "set" commands.
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;

use DateTime;
use stdClass;

/**
 * Implements the "set" API endpoints.
 */
class api_set extends api {

    /** @var string Target object type. */
    protected $object;

    /** @var bool Whether to update the gradebook. */
    protected $gradebook = false;

    /**
     * Constructor.
     *
     * @param array|object $param Parameters from API request.
     */
    public function __construct($param) {
        $this->mode = "set";
        $this->param = (object)$param;
    }

    /**
     * Validate and normalize parameters.
     *
     * @return void|array Error structure if invalid.
     */
    protected function check_parameters() {
        if (empty($this->param->object)) {
            return $this->error("error", "object is missing");
        }
        if (empty($this->param->data)) {
            return $this->error("error", "data are missing");
        }
        if (!is_object($this->param->data)) {
            return $this->error("error", "incorrect data");
        }

        $this->object = $this->param->object;
        if (!isset($this->param->extend)) {
            $this->param->extend = 0;
        }
        if (!isset($this->param->gradebook)) {
            $this->param->gradebook = 0;
        }

        if (!isset($this->param->previous)) {
            $this->param->previous = new stdClass();
            $previous = 0;
        } else {
            $previous = 1;
        }

        if (!empty($this->param->gradebook) && $this->param->gradebook == 0) {
            $gradebook = 1;
        } else {
            $gradebook = 0;
        }

        field::features($previous, $this->param->extend, $gradebook);
    }

    /**
     * Execute the correct "set" operation.
     *
     * @return object Result object.
     */
    protected function do() {
        $method = "do_" . $this->object;
        if (!method_exists($this, $method)) {
            return $this->error("error", "object '$this->object' unknown");
        }
        return $this->$method();
    }

    /**
     * Finalize update process, collect errors and extended fields.
     *
     * @param object $entity Entity being updated.
     * @return void
     */
    protected function end($entity) {
        $entity->update($this->param->data, $this->param->previous);

        $errors = $entity->get_errors();
        if (!empty($errors)) {
            $this->answer->errors = $errors;
            $this->answer->code = "partial";
        } else {
            $this->answer->code = "ok";
        }

        $extended = dbinfos::get_extensions();
        if (!empty($extended)) {
            $this->answer->extended = $extended;
        }
    }

    /**
     * Update course entity.
     *
     * @return object Result.
     */
    protected function do_course() {
        if (empty($this->param->courseid)) {
            return $this->error("error", "courseid must be provided");
        }
        if (empty($this->param->shortname)) {
            return $this->error("error", "shortname must be provided");
        }

        $course = new course($this->param->courseid);
        if (!$course->is()) {
            return $this->error("notfound", "course not found");
        }
        if ($this->param->shortname !== $course->shortname) {
            return $this->error("notfound", "incorrect shortname");
        }

        if (!$course->allowed()) {
            return $this->error("restricted");
        }

        $course = new \local_ezglobe\entities\course($course->get());
        $this->end($course);

        \course_modinfo::purge_course_cache($this->param->courseid);
    }

    /**
     * Update section entity.
     *
     * @return object Result.
     */
    protected function do_section() {
        if (empty($this->param->courseid)) {
            return $this->error("error", "courseid must be provided");
        }
        if (empty($this->param->sectionid)) {
            return $this->error("error", "sectionid must be provided");
        }

        $course = new course($this->param->courseid);
        if (!$course->is()) {
            return $this->error("notfound", "course not found");
        }

        $sql = "SELECT * FROM {course_sections} WHERE course = :course AND id = :sectionid";
        $section = database::load_one($sql, [
            "course" => $this->param->courseid,
            "sectionid" => $this->param->sectionid,
        ]);
        if (empty($section)) {
            return $this->error("notfound", "section not found");
        }

        if (!$course->allowed()) {
            return $this->error("restricted");
        }

        $section = new \local_ezglobe\entities\section($section);
        $this->end($section);

        \course_modinfo::purge_course_cache($this->param->courseid);
    }

    /**
     * Update module entity.
     *
     * @return object Result.
     */
    protected function do_module() {
        if (empty($this->param->courseid)) {
            return $this->error("error", "courseid must be provided");
        }
        if (empty($this->param->module)) {
            return $this->error("error", "module name must be provided");
        }
        if (empty($this->param->cmid)) {
            return $this->error("error", "cmid must be provided");
        }

        $course = new course($this->param->courseid);
        if (!$course->is()) {
            return $this->error("notfound", "module not found");
        }
        if (!$course->allowed()) {
            return $this->error("restricted");
        }

        $module = database::get("course_modules", $this->param->cmid);
        if (empty($module) || $module->course != $this->param->courseid) {
            return $this->error("notfound", "module not found");
        }
        if (entity::module_name($module->module) !== $this->param->module) {
            return $this->error("notfound", "module is not a " . $this->param->module);
        }

        $class = "\\local_ezglobe\\entities\\" . entity::module_name($module->module);
        if (class_exists($class)) {
            $module = new $class($module->instance, null, []);
        } else {
            $module = new entity(
                $module->instance,
                entity::module_name($module->module),
                ["name", "intro"]
            );
        }

        $this->end($module);
        \course_modinfo::purge_course_cache($this->param->courseid);
    }

    /**
     * Update question entity.
     *
     * @return object Result.
     */
    protected function do_question() {
        if (empty($this->param->categoryid)) {
            return $this->error("error", "categoryid must be provided");
        }
        if (!isset($this->param->questionid)) {
            return $this->error("error", "questionid must be provided");
        }
        if (get_config("local_ezglobe", "questions") == 0) {
            return $this->error("restricted");
        }

        $question = database::get("question", $this->param->questionid);
        if (empty($question)) {
            return $this->error("notfound");
        }

        $version = database::get("question_versions", $this->param->questionid, "questionid");
        if (empty($version)) {
            return $this->error("notfound", "no version");
        }

        $bank = database::get("question_bank_entries", $version->questionbankentryid);
        if (empty($bank) || $bank->questioncategoryid != $this->param->categoryid) {
            return $this->error("notfound", "wrong category");
        }

        $question = new \local_ezglobe\entities\question($question);
        $this->end($question);
    }

    /**
     * Update tag entity.
     *
     * @return object Result.
     */
    protected function do_tag() {
        if (empty($this->param->id)) {
            return $this->error("error", "id must be provided");
        }
        if (get_config("local_ezglobe", "tags") == 0) {
            return $this->error("restricted");
        }

        $tag = database::get("tag", $this->param->id);
        if (empty($tag)) {
            return $this->error("notfound");
        }

        $tag = new \local_ezglobe\entities\tag($tag);
        $this->end($tag);
    }
}
