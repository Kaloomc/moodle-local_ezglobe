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
 * Entity class for the workshop activity.
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
 * Represents a workshop entity for API handling.
 */
class workshop extends entity {

    /**
     * The main DB table for workshop entities.
     *
     * @var string
     */
    protected $maintable = 'workshop';

    /**
     * Define the fields and related entities for workshop.
     *
     * @return void
     */
    protected function define_fields(): void {
        $this->addFields('name', 'intro', 'instructauthors', 'instructreviewers', 'conclusion');
        $this->fields['name']->gradebook();
        $this->addEntitiesFromTable('accumulatives', ['description'], 'workshopform_accumulative', 'workshopid');
        $this->addEntitiesFromTable('aspects', ['description'], 'workshopform_comments', 'workshopid');
        $this->addEntitiesFromTable('numerrors', ['description', 'grade0', 'grade1'], 'workshopform_numerrors', 'workshopid');
        $this->addEntitiesFromTable('rubrics', 'workshop_rubric', 'workshopform_rubric', 'workshopid');
    }
}
