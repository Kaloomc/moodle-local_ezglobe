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
 * Entity class for quiz activity.
 *
 * @package    local_ezxlate
 * @subpackage entities
 * @copyright  2025 CBCD EURL & Ezxlate
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate\entities;

use local_ezxlate\entity;

/**
 * Represents a Quiz activity entity.
 */
class quiz extends entity {

    /**
     * The main DB table for the quiz entity.
     *
     * @var string
     */
    protected $maintable = 'quiz';

    /**
     * Define the fields and related entities for the quiz.
     *
     * @return void
     */
    protected function define_fields(): void {
        $this->addFields('name', 'intro');
        $this->fields['name']->gradebook();
        $this->addEntitiesFromTable('feedback', ['feedbacktext'], 'quiz_feedback', 'quizid');
        $this->addEntitiesFromTable('grade_items', ['name'], 'quiz_grade_items', 'quizid');
        $this->addEntitiesFromTable('sections', ['heading'], 'quiz_sections', 'quizid');
    }
}
