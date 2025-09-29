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
 * Class to manage the activity "lesson"
 *
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe\entities;

class lesson extends \local_ezglobe\entity {
    
    protected $mainTable = "lesson";       // Table name
    
    protected function defineFields() {
        $this->addFields("name", "intro");
        $this->fields["name"]->gradebook();
        $this->addEntitiesFromTable("pages",  [ "title", "contents"], "lesson_pages", "lessonid");
        $this->addEntitiesFromTable("answers",  [ "answer", "response"], "lesson_answers", "lessonid");
    }

    
}


