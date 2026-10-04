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
 * Settings for block_zoomattendance.
 *
 * @package    block_zoomattendance
 * @copyright  2026 Mezbaur Are Rafi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_zoomattendance/days',
        new lang_string('days', 'block_zoomattendance'),
        new lang_string('days_desc', 'block_zoomattendance'),
        30,
        PARAM_INT
    ));
    // Colours follow the thresholds of Zoom attendance itself.
    $settings->add(new admin_setting_description(
        'block_zoomattendance/colours',
        '',
        new lang_string('colours_desc', 'block_zoomattendance')
    ));
}
