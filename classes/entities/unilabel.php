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
 * Entity class for the "unilabel" activity.
 *
 * @package    local_ezxlate
 * @subpackage entities
 * @copyright  2025 CBCD EURL & Ezxlate
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate\entities;

use local_ezxlate\database;
use local_ezxlate\entities;
use local_ezxlate\entity;

/**
 * Represents a unilabel activity entity.
 */
class unilabel extends entity {

    /**
     * The main DB table for the entity.
     *
     * @var string
     */
    protected $maintable = 'unilabel';

    /**
     * Define the fields and relationships for the entity.
     *
     * @return void
     */
    protected function define_fields(): void {
        $this->addFields('name', 'intro');
        $this->fields['name']->gradebook();

        // Accordion type.
        if ($this->record('unilabeltype') === 'accordion') {
            $record = database::get('unilabeltype_accordion', $this->id, 'unilabelid');
            if (!empty($record)) {
                $values = database::getAll('unilabeltype_accordion_seg', $record->id, 'accordionid');
                $this->fields['segments'] =
                    new entities($values, ['unilabeltype_accordion_seg', 'heading', 'content'], 'id');
            }
        }

        // Carousel type.
        if ($this->record('unilabeltype') === 'carousel') {
            $record = database::get('unilabeltype_carousel', $this->id, 'unilabelid');
            if (!empty($record)) {
                $values = database::getAll('unilabeltype_carousel_slide', $record->id, 'carouselid');
                $this->fields['slides'] =
                    new entities($values, ['unilabeltype_carousel_slide', 'caption'], 'id');
            }
        }

        // Grid type.
        if ($this->record('unilabeltype') === 'grid') {
            $record = database::get('unilabeltype_grid', $this->id, 'unilabelid');
            if (!empty($record)) {
                $values = database::getAll('unilabeltype_grid_tile', $record->id, 'gridid');
                $this->fields['tiles'] =
                    new entities($values, ['unilabeltype_grid_tile', 'title', 'content'], 'id');
            }
        }
    }
}
