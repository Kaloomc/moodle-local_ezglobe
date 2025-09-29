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
 * Entity class for questionnaire sections.
 *
 * @package    local_ezglobe
 * @subpackage entities
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezglobe\entities;

use local_ezglobe\entity;

/**
 * Represents a questionnaire section entity for API handling.
 */
class questionnaire_section extends entity {

    /**
     * The main DB table for questionnaire sections.
     *
     * @var string
     */
    protected $maintable = 'questionnaire_fb_sections';

    /**
     * Define the fields and related entities for a questionnaire section.
     *
     * @return void
     */
    protected function define_fields(): void {
        $this->addFields('sectionheading');
        $this->addEntitiesFromTable('feedbacks', ['feedbacktext'], 'questionnaire_feedback', 'sectionid');
    }
}
