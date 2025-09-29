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
 * Class to manage api with "set" command
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe;
use \DateTime;
use \stdClass;

class api_set extends api {
    
    protected $object;
    protected $gradebook = false;       // True to update gradebook

    
    function __construct($param) {
        $this->mode = "set";
        $this->param = (object) $param;
    }
    
    
    
    protected function checkParameters() {
        // Analyse parameters
        // Return error message or null
        if (empty($this->param->object)) return $this->error("error", "object is missing");
        if (empty($this->param->data)) return $this->error("error", "data are missing");
        if (!is_object($this->param->data)) return $this->error("error", "incorrect data");
        $this->object = $this->param->object;
        if ( ! isset($this->param->extend)) $this->param->extend = 0;
        if ( ! isset($this->param->gradebook)) $this->param->gradebook = 0;
        if ( ! isset($this->param->previous)) {
            $this->param->previous = new \stdClass();
            $previous = 0;
        } else $previous = 1;
        if ( ! empty($this->param->gradebook) and $this->param->gradebook == 0) $gradebook = 1;
        else $gradebook = 0;
        field::features( $previous, $this->param->extend, $gradebook);
    }
    
    protected function do() {
        // Process the precise action
        // Return a std object with code and other properties
        
        $method = "do_" . $this->object;
        if (!method_exists($this, $method)) return $this->error("error", "object '$this->object' unknown");
        return $this->$method();

    }
   
    
    protected function end($entity) {
        $entity->update($this->param->data, $this->param->previous);
        $errors = $entity->getErrors();
        if (!empty($errors)) {
            $this->answer->errors = $errors;
            $this->answer->code = "partial";
        } else $this->answer->code = "ok";
        
        $extended = dbinfos::getExtensions();
        if (!empty($extended)) $this->answer->extended = $extended;
    }
    
    protected function do_course() {
        if (empty($this->param->courseid)) return $this->error("error", "courseid must be provided");
        if (empty($this->param->shortname)) return $this->error("error", "shortname must be provided");
        
        $course = new course($this->param->courseid);
        if (!$course->is()) return $this->error("notfound", "course not found");
        if ($this->param->shortname != $course->shortname) return $this->error("notfound", "incorrect shortname");
        
        if (!$course->allowed()) return $this->error("restricted");        
        $course = new \local_ezglobe\entities\course($course->get());
        
        $this->end($course);
        \course_modinfo::purge_course_cache($this->param->courseid);
                
    }
    
    protected function do_section() {
        if (empty($this->param->courseid)) return $this->error("error", "courseid must be provided");
        if (empty($this->param->sectionid)) return $this->error("error", "sectionid must be provided");
        
        $course = new course($this->param->courseid);
        if (!$course->is()) return $this->error("notfound", "course not found");
        
        $sql = "SELECT * FROM {course_sections} WHERE course = :course and id = :sectionid";
        $section = database::loadOne($sql, [ "course" => $this->param->courseid, "sectionid" => $this->param->sectionid] );
        if (empty($section)) return $this->error("notfound", "section not found");
        
        if (!$course->allowed()) return $this->error("restricted");        
        $section = new \local_ezglobe\entities\section($section);
        
        $this->end($section);
        \course_modinfo::purge_course_cache($this->param->courseid);
                
    }
    
    protected function do_module() {
        if (empty($this->param->courseid)) return $this->error("error", "courseid must be provided");
        if (empty($this->param->module)) return $this->error("error", "module name must be provided");
        if (empty($this->param->cmid)) return $this->error("error", "cmid must be provided");
        $course = new course($this->param->courseid);
        if (!$course->is()) return $this->error("notfound", "module not found");
        if (!$course->allowed()) return $this->error("restricted");
        
        
        $module = database::get("course_modules", $this->param->cmid);
        if (empty($module) 
                or $module->course != $this->param->courseid)
            $this->error("notfound", "module not found");
        if ( entity::moduleName($module->module) != $this->param->module) 
            return $this->error("notfound", "module is not a " . $this->param->module);
               
        $class = "\\local_ezglobe\\entities\\" . entity::moduleName($module->module);
        if ( class_exists($class)) $module = new $class($module->instance, null, []);
        else $module = new entity($module->instance, entity::moduleName($module->module), [ "name", "intro"]);
        
        $this->end($module);
        \course_modinfo::purge_course_cache($this->param->courseid);
        
    }
    
    protected function do_question() {
        if (empty($this->param->categoryid)) return $this->error("error", "categoryid must be provided");
        if ( ! isset($this->param->questionid)) return $this->error("error", "questionid must be provided");
        if (get_config("local_ezglobe", "questions") == 0) return $this->error("restricted");
        
        $question = database::get("question", $this->param->questionid);
        if (empty($question)) return $this->error("notfound");
        $version = database::get("question_versions", $this->param->questionid, "questionid");
        if (empty($version)) return $this->error("notfound", "no version");
        $bank = database::get("question_bank_entries", $version->questionbankentryid);
        if ( empty($bank) or $bank->questioncategoryid != $this->param->categoryid)
            return $this->error("notfound", "wrong category");
        
        $question = new \local_ezglobe\entities\question($question);       
        $this->end($question);
    }
 
    
    protected function do_tag() {
        if (empty($this->param->id)) return $this->error("error", "id must be provided");
        if (get_config("local_ezglobe", "tags") == 0) return $this->error("restricted");

        $tag = database::get("tag", $this->param->id);  
        if (empty($tag)) $this->error("notfound");
        $tag = new \local_ezglobe\entities\tag($tag);       
        $this->end($tag);
        

    }
    
}
