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
 * Moodle app support for block_zoomattendance.
 *
 * @package    block_zoomattendance
 * @copyright  2026 Mezbaur Are Rafi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'block_zoomattendance' => [
        'handlers' => [
            // The block on the app's dashboard, with the same sections as on the web.
            'zoomattendance' => [
                'delegate' => 'CoreBlockDelegate',
                'method' => 'mobile_block_view',
                'displaydata' => [
                    'title' => 'pluginname',
                    'class' => 'block_zoomattendance',
                    'type' => 'template',
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'block_zoomattendance'],
        ],
    ],
];
