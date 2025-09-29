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
 * Entity class for the activity "stickynotes".
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
 * Represents a stickynotes activity entity.
 */
class stickynotes extends entity {

    /**
     * The main DB table for the stickynotes entity.
     *
     * @var string
     */
    protected $main_table = 'stickynotes';

    /**
     * Define the fields and related entities for the stickynotes activity.
     *
     * @return void
     */
    protected function define_fields(): void {
        $this->addFields(
            'name',
            'intro',
            'color1_meaning',
            'color2_meaning',
            'color3_meaning',
            'color4_meaning',
            'color5_meaning',
            'color6_meaning'
        );

        $this->fields['name']->gradebook();

        $this->addEntitiesFromTable(
            'columns',
            ['title'],
            'stickynotes_column',
            'stikyid'
        );
    }
}
