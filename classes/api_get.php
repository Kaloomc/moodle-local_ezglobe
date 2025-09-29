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
 * API class to handle "get" commands.
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
 * Handles API requests for "get" mode.
 */
class api_get extends api {

    /** @var string Action to execute. */
    protected $action;

    /**
     * Constructor.
     *
     * @param array|object $param Parameters for the request.
     */
    public function __construct($param) {
        $this->mode = "get";
        $this->param = (object) $param;
    }

    /**
     * Check parameters validity.
     *
     * @return string|null Error message or null.
     */
    protected function check_parameters(): ?string {
        if (empty($this->param->action)) {
            return $this->error("error", "action is missing");
        }
        $this->action = $this->param->action;
        return null;
    }

    /**
     * Execute the action.
     *
     * @return object Result object.
     */
    protected function do(): object {
        $method = "do_" . $this->action;
        if (!method_exists($this, $method)) {
            return $this->error("error", "action '{$this->action}' unknown");
        }
        return $this->$method();
    }

    /**
     * Get a course.
     *
     * @return object Result object.
     */
    protected function do_course(): object {
        if (!empty($this->param->courseid)) {
            $course = new course($this->param->courseid);
        } else if (!empty($this->param->shortname)) {
            $course = new course($this->param->shortname);
        } else {
            return $this->error("error", "courseid or shortname must be provided");
        }

        if (!$course->is()) {
            return $this->error("notfound", "course not found");
        }

        if (!empty($this->param->courseid) && !empty($this->param->shortname)) {
            if ($this->param->shortname !== $course->shortname) {
                return $this->error("notfound", "course not found");
            }
        }

        if (!$course->allowed()) {
            return $this->error("restricted");
        }

        $entitycourse = new \local_ezglobe\entities\course($course->get());
        $this->data = $entitycourse->get();

        return (object) ["code" => "ok", "data" => $this->data];
    }

    /**
     * Get a module.
     *
     * @return object Result object.
     */
    protected function do_module(): object {
        if (empty($this->param->courseid)) {
            return $this->error("error", "courseid must be provided");
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

        $infosfields = [
            "courseid" => $module->course,
            "module" => entity::module_name($module->module),
            "cmid" => $module->id,
        ];

        $classname = "\\local_ezglobe\\entities\\" . entity::module_name($module->module);
        if (class_exists($classname)) {
            $module = new $classname($module->instance, null, [], $infosfields);
        } else {
            $module = new entity(
                $module->instance,
                entity::module_name($module->module),
                ["name", "intro"],
                $infosfields
            );
        }

        $this->data = $module->get();
        return (object) ["code" => "ok", "data" => $this->data];
    }

    /**
     * Get question categories.
     *
     * @return object Result object.
     */
    protected function do_questioncategories(): object {
        if (get_config("local_ezglobe", "questions") == 0) {
            return $this->error("restricted");
        }

        $contexts = [
            10 => "system",
            30 => "user",
            40 => "coursecat",
            50 => "course",
            60 => "group",
            70 => "module",
            80 => "block",
        ];

        foreach (database::get_all("question_categories") as $categ) {
            $id = $categ->id;
            $questionbank = database::get("question_bank_entries", $id, "questioncategoryid");
            if (empty($questionbank)) {
                continue;
            }

            $this->data->{$id} = ["name" => $categ->name];
            $context = database::get("context", $categ->contextid);

            if (empty($context) || empty($contexts[$context->contextlevel])) {
                continue;
            }

            $this->data->{$id}["context"] = $contexts[$context->contextlevel];
            if ($context->contextlevel != 10) {
                $this->data->{$id}["instanceid"] = $context->instanceid;
            }
        }

        return (object) ["code" => "ok", "data" => $this->data];
    }

    /**
     * Get questions.
     *
     * @return object Result object.
     */
    protected function do_questions(): object {
        if (get_config("local_ezglobe", "questions") == 0) {
            return $this->error("restricted");
        }
        if (empty($this->param->categoryid)) {
            return $this->error("error", "categoryid must be provided");
        }

        $last = (isset($this->param->versions) && $this->param->versions === "last");

        $questions = new stdClass();
        foreach (database::get_all("question_bank_entries", $this->param->categoryid, "questioncategoryid") as $questionbank) {
            $sql = "SELECT {question}.*
                      FROM {question}
                 LEFT JOIN {question_versions}
                        ON {question_versions}.questionid = {question}.id
                     WHERE questionbankentryid = :qb";
            if ($last) {
                $sql .= " ORDER BY version DESC LIMIT 1";
            }

            foreach (database::load_multiple($sql, ["qb" => $questionbank->id]) as $record) {
                $question = new \local_ezglobe\entities\question($record);
                $qid = $record->id;
                $questions->{$qid} = $question->get();
            }
        }

        $this->data->categoryid = $this->param->categoryid;
        if (!empty((array) $questions)) {
            $this->data->questions = $questions;
        }

        return (object) ["code" => "ok", "data" => $this->data];
    }

    /**
     * Get tags.
     *
     * @return object Result object.
     */
    protected function do_tags(): object {
        if (get_config("local_ezglobe", "tags") == 0) {
            return $this->error("restricted");
        }

        foreach (database::get_all("tag") as $tag) {
            $id = $tag->id;
            $entitytag = new \local_ezglobe\entities\tag($tag);
            $this->data->{$id} = $entitytag->get();
        }

        return (object) ["code" => "ok", "data" => $this->data];
    }
}
