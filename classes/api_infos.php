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
 * API class to handle the "infos" command.
 *
 * @package    local_ezxlate
 * @copyright  2025 CBCD EURL & Ezxlate
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate;

use DateTime;
use stdClass;

/**
 * Implementation of the API command "infos".
 */
class api_infos extends api {

    /**
     * Constructor.
     *
     * @param array|stdClass $param Parameters passed to the API.
     */
    public function __construct($param) {
        $this->mode = 'infos';
        $this->param = (object) $param;
    }

    /**
     * Execute the "infos" API action.
     *
     * @return stdClass|null
     */
    protected function do(): ?stdClass {
        $this->answer->version = $this->version();
        $this->answer->previousverification = (get_config('local_ezxlate', 'previous') == 1 ? 1 : 0);
        $this->answer->fieldsextension = (dbinfos::canExtend() ? 1 : 0);
        $this->answer->fieldssize = (dbinfos::canTechnicalExtend() ? 1 : 0);
        $this->answer->gradebook = (get_config('local_ezxlate', 'gradebook') == 1 ? 1 : 0);

        return $this->answer;
    }
}
