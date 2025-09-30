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
 * Generic API management class.
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
 * Base class for handling API requests and responses.
 */
class api {

    /**
     * Current mode of the API.
     *
     * @var string
     */
    protected $mode = '';

    /**
     * Parameters of the request.
     *
     * @var stdClass|null
     */
    protected $param = null;

    /**
     * Data container for the response.
     *
     * @var stdClass|null
     */
    protected $data = null;

    /**
     * Main API response object.
     *
     * @var stdClass|null
     */
    protected $answer = null;

    /**
     * Build a failed API response.
     *
     * @param string $code Error code.
     * @param string $message Error message.
     * @return stdClass
     */
    public static function failed(string $code = 'error', string $message = ''): stdClass {
        $answer = new stdClass();
        $answer->code = $code;
        if (!empty($message)) {
            $answer->message = $message;
        }
        return $answer;
    }

    /**
     * Process the API request.
     *
     * @return stdClass
     */
    public function process(): stdClass {
        $this->data = new stdClass();
        $this->answer = new stdClass();
        $this->answer->code = 'ok';

        $message = $this->check_authentification();
        if (!empty($message)) {
            return $this->error('auth', $message);
        }

        $answer = $this->check_parameters();
        if (!empty($answer)) {
            return $answer;
        }

        $answer = $this->do();
        if (empty($answer)) {
            $answer = $this->answer;
        }

        if (empty($answer->data) && !empty((array) $this->data)) {
            $answer->data = $this->data;
        }

        return $answer;
    }

    /**
     * Validate request parameters.
     *
     * Should be overloaded by subclasses.
     *
     * @return stdClass|null
     */
    protected function check_parameters(): ?stdClass {
        return null;
    }

    /**
     * Execute the main logic of the API.
     *
     * Should be overloaded by subclasses.
     *
     * @return stdClass|null
     */
    protected function do(): ?stdClass {
        return null;
    }

    /**
     * Build an error response.
     *
     * @param string $code Error code.
     * @param string $message Error message.
     * @return stdClass
     */
    public function error(string $code = 'error', string $message = ''): stdClass {
        $answer = new stdClass();
        $answer->code = $code;
        if (!empty($message)) {
            $answer->message = $message;
        }
        return $answer;
    }

    /**
     * Check API authentication.
     *
     * @return string Error message or empty string if valid.
     */
    protected function check_authentification(): string {
        if (get_config('local_ezxlate', 'open') != 1) {
            return 'API disabled';
        }
        if (empty(get_config('local_ezxlate', 'key'))) {
            return 'Empty key, API disabled';
        }
        if (strlen(get_config('local_ezxlate', 'key')) < 10) {
            return 'Key is too short, API disabled';
        }
        if (empty($this->param->key)) {
            return 'Key not provided in the request';
        }
        if ($this->param->key != get_config('local_ezxlate', 'key')) {
            return 'Authentication failed';
        }
        if ($this->iprestricted()) {
            return 'Your IP address ' . strtolower(trim($_SERVER['REMOTE_ADDR'])) . ' is not allowed';
        }
        return '';
    }

    /**
     * Check if current IP is restricted.
     *
     * @return bool
     */
    protected function iprestricted(): bool {
        $ips = [];
        foreach (explode("\n", str_replace(',', "\n", get_config('local_ezxlate', 'ips'))) as $ip) {
            $ip = strtolower(trim($ip));
            if (!empty($ip)) {
                $ips[] = $ip;
            }
        }
        if (empty($ips)) {
            return false;
        }

        $myip = strtolower(trim($_SERVER['REMOTE_ADDR']));
        foreach ($ips as $ip) {
            if ($myip === $ip) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get the plugin version.
     *
     * @return int|null
     */
    public function version(): ?int {
        global $CFG;
        $pluginpath = $CFG->dirroot . '/local/ezxlate/version.php';
        if (file_exists($pluginpath)) {
            $plugin = new stdClass();
            include($pluginpath);
            return $plugin->version ?? null;
        }
        return null;
    }
}
