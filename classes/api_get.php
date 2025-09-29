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
 * Class to manage api with "get" command
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;
use \DateTime;
use \stdClass;

class api_get extends api {
    
    protected $action;
    
    function __construct($param) {
        $this->mode = "get";
        $this->param = (object) $param;
    }
    
    
    
    protected function checkParameters() {
        // Analyse parameters
        // Return error message or null
        if (empty($this->param->action)) return $this->error("error", "action is missing");
        $this->action = $this->param->action;
    }
    
    protected function do() {
        // Process the precise action
        // Return a std object with code and other properties
        
        $method = "do_" . $this->action;
        if (!method_exists($this, $method)) return $this->error("error", "action '$this->action' unknown");
        return $this->$method();
        
    }
    
    protected function do_course() {
        if (!empty($this->param->courseid)) $course = new course($this->param->courseid);
        else if (!empty($this->param->shortname)) $course = new course($this->param->shortname);
        else return $this->error("error", "courseid or shortname must be provided");
        if (!$course->is()) return $this->error("notfound", "course not found");
        if (!empty($this->param->courseid) and !empty($this->param->shortname)) {
            if ($this->param->shortname != $course->shortname) return $this->error("notfound", "course not found");
        }
        
        if (!$course->allowed()) return $this->error("restricted");
        
        $course = new \local_ezglobe\entities\course($course->get());

        $this->data = $course->get();
    }
    
    protected function do_module() {
        if (empty($this->param->courseid)) return $this->error("error", "courseid must be provided");
        if (empty($this->param->cmid)) return $this->error("error", "cmid must be provided");
        $course = new course($this->param->courseid);
        if (!$course->is()) return $this->error("notfound", "module not found");
        if (!$course->allowed()) return $this->error("restricted");
        
        
        $module = database::get("course_modules", $this->param->cmid);  
        if (empty($module) or $module->course != $this->param->courseid)
            return $this->error("notfound", "module not found");

        
        $infosFields = [
            "courseid" => $module->course,
            "module" => entity::moduleName($module->module),
            "cmid" => $module->id
        ];
        
        $class = "\\local_ezglobe\\entities\\" . entity::moduleName($module->module);
        if ( class_exists($class)) $module = new $class($module->instance, null, [], $infosFields);
        else $module = new entity($module->instance, entity::moduleName($module->module), [ "name", "intro"], $infosFields);

        $this->data = $module->get();
        
    }
    
    protected function do_questionCategories() {

        if (get_config("local_ezglobe", "questions") == 0) return $this->error("restricted");
        
        $contexts = [ 
                10 => "system",
                30 => "user",
                40 => "coursecat",
                50 => "course",
                60 => "group",
                70 => "module",
                80 => "block"
            
            ];
        
        foreach(database::getAll("question_categories") as $categ) {
            $id = $categ->id;
            $question1 = database::get("question_bank_entries", $id, "questioncategoryid");
            if (empty($question1)) continue;
            $this->data->$id = [ "name" => $categ->name ];
            $context = database::get("context", $categ->contextid);
            if (empty($context) or empty($contexts[$context->contextlevel])) continue;
            $this->data->$id["context"] = $contexts[$context->contextlevel];
            if ($context->contextlevel != 10) $this->data->$id["instanceid"] = $context->instanceid;
          }
    }
 
    protected function do_questions() {
        if (get_config("local_ezglobe", "questions") == 0) return $this->error("restricted");
        if (empty($this->param->categoryid)) return $this->error("error", "categoryid must be provided");
        if (isset($this->param->versions) and $this->param->versions == "last") $last = true; else $last = false;        
        
        $questions = new \stdClass();
        foreach(database::getAll("question_bank_entries", $this->param->categoryid, "questioncategoryid") as $questionBank) {
            $sql = "SELECT {question}.* FROM {question} LEFT JOIN {question_versions} ON {question_versions}.questionid = {question}.id "
                    . " WHERE questionbankentryid = :qb ";
            if ($last) $sql .= " ORDER BY version DESC LIMIT 1";
            foreach(database::loadMultiple($sql, ["qb" => $questionBank->id]) as $record) {              
                $question = new \local_ezglobe\entities\question($record);
                $qid = $record->id;
                $questions->$qid = $question->get();
            }
        }
        $this->data->categoryid = $this->param->categoryid;
        if (!empty($questions)) $this->data->questions = $questions;
    }
    
    protected function do_tags() {
        if (get_config("local_ezglobe", "tags") == 0) return $this->error("restricted");
        foreach(database::getAll("tag") as $tag) {    
            $id = $tag->id;
            $tag = new \local_ezglobe\entities\tag($tag);
            $this->data->$id = $tag->get();
        }

    }
    
}
