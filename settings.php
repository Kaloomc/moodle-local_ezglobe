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
 * @package    local_ezglobe
 * @copyright  2025 CBCD EURL & EzGlobe
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
     
    $settings = new admin_settingpage( 'local_ezglobe', get_string('config:title', 'local_ezglobe') );
    
    // Prepare a random key
    if (get_config("local_ezglobe", "key") == "*") {
        $secret = ""; 
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMONPQRSTUVWXYZ';
        $lim = strlen($chars) -1;
        for($n=1; $n<=50; $n++) {
            $secret .= $chars[mt_rand(0,$lim )];
        }
        set_config("key", $secret, "local_ezglobe"); 
    }
    
    // Open : yes / no
    $settings->add(new admin_setting_configselect(
        'local_ezglobe/open', 
        get_string('config:open:label', 'local_ezglobe'), 
        get_string('config:open:desc', 'local_ezglobe'), 
        0, 
        [ 
            1 => get_string('yes'),
            0 => get_string('no')
        ]
    ));
    
        
    // secret Key
    $settings->add(new admin_setting_configtext(
        'local_ezglobe/key', 
        get_string('config:key:label', 'local_ezglobe'), 
        get_string('config:key:desc', 'local_ezglobe'), 
        ""));

    
    // Allowed IPs
    $settings->add(new admin_setting_configiplist(
        'local_ezglobe/ips', 
        get_string('config:ips:label', 'local_ezglobe'), 
        get_string('config:ips:desc', 'local_ezglobe'),
        ""));

        
    // Verification of previous value required : yes / no
    $settings->add(new admin_setting_configselect(
        'local_ezglobe/previous', 
        get_string('config:previous:label', 'local_ezglobe'), 
        get_string('config:previous:desc', 'local_ezglobe'), 
        0, 
        [ 
            1 => get_string('yes'),
            0 => get_string('no')
        ]
    ));
    
    // Verif if we can techically extend
    if (\local_ezglobe\dbinfos::canTechnicalExtend()) {
        // Can extend fields (yes/no)
        $settings->add(new admin_setting_configselect(
            'local_ezglobe/extend', 
            get_string('config:extend:label', 'local_ezglobe'), 
            get_string('config:extend:desc', 'local_ezglobe'), 
            0, 
            [ 
                1 => get_string('yes'),
                0 => get_string('no')
            ]
        ));
    } else {
        // Force "no"
        $settings->add(new admin_setting_configselect(
            'local_ezglobe/extend', 
            get_string('config:extend:label', 'local_ezglobe'), 
            get_string('config:extend:impossible', 'local_ezglobe'), 
            0, 
            [ 
                0 => get_string('no')
            ]
        ));        
    }
    
    // gradebook
    $settings->add(new admin_setting_configselect(
        'local_ezglobe/gradebook', 
        get_string('config:gradebook:label', 'local_ezglobe'), 
        get_string('config:gradebook:desc', 'local_ezglobe'), 
        0, 
        [ 
            1 => get_string('yes'),
            0 => get_string('no')
        ]
    ));


    // Export et import questions texts
    $settings->add(new admin_setting_configselect(
        'local_ezglobe/questions', 
        get_string('config:questions:label', 'local_ezglobe'), 
        get_string('config:questions:desc', 'local_ezglobe'), 
        0, 
        [ 
            1 => get_string('yes'),
            0 => get_string('no')
        ]
    ));
    
    // Export et import tags texts
    $settings->add(new admin_setting_configselect(
        'local_ezglobe/tags', 
        get_string('config:tags:label', 'local_ezglobe'), 
        get_string('config:tags:desc', 'local_ezglobe'), 
        0, 
        [ 
            1 => get_string('yes'),
            0 => get_string('no')
        ]
    ));
    
    // Allowed courses
    $settings->add(new admin_setting_configtextarea(
        'local_ezglobe/allowed_courses', 
        get_string('config:allowed_courses:label', 'local_ezglobe'), 
        get_string('config:allowed_courses:desc', 'local_ezglobe'),
        ""));
    
    // Restricted courses
    $settings->add(new admin_setting_configtextarea(
        'local_ezglobe/restricted_courses', 
        get_string('config:restricted_courses:label', 'local_ezglobe'), 
        get_string('config:restricted_courses:desc', 'local_ezglobe'),
        ""));

    
    $ADMIN->add( 'localplugins', $settings );
    
}
