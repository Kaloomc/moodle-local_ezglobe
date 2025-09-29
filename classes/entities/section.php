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
 * Entity class for course sections.
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
 * Represents a course section entity.
 */
class section extends entity {

    /**
     * The main DB table for the section entity.
     *
     * @var string
     */
    protected $main_table = 'course_sections';

    /**
     * Define the fields and related entities for the course section.
     *
     * @return void
     */
    protected function define_fields(): void {
        $this->addFields('name', 'summary');

        $modules = [];
        foreach (explode(',', $this->record('sequence')) as $cmid) {
            $module_name = $this->getModuleName($cmid);
            if (!empty($module_name)) {
                $modules[$cmid] = $module_name;
            }
        }

        $this->addDirect('modules', $modules)->onlyGet();
    }
}
