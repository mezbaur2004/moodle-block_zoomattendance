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
 * English strings for block_zoomattendance.
 *
 * @package    block_zoomattendance
 * @copyright  2026 Mezbaur Are Rafi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allabove'] = 'All {$a} on track';
$string['allteachers'] = 'All teachers ({$a}) ›';
$string['cachedef_content'] = 'Each user\'s Zoom attendance block content';
$string['classescount'] = 'Classes: {$a}';
$string['days'] = 'Teacher period (days)';
$string['days_desc'] = 'The teacher sections cover classes from this many days ago until today.';
$string['low'] = 'Low';
$string['lowcount'] = '{$a->low} of {$a->total} low';
$string['mine'] = 'My attendance';
$string['minehelp'] = 'Course overall. The line marks {$a}%.';
$string['nothing'] = 'Nothing to show: this account has no Zoom attendance yet.';
$string['pluginname'] = 'Zoom attendance';
$string['privacy:metadata'] = 'The Zoom attendance block stores no personal data. It shows data from the Zoom attendance local plugin.';
$string['refreshes'] = 'Refreshes hourly, after each attendance sync.';
$string['studentsheading'] = 'My students';
$string['studentshelp'] = 'Students under {$a}% course overall';
$string['teachersheading'] = 'Teacher attendance';
$string['teachershelp'] = 'Last {$a->days} days, lowest first. The line marks {$a->threshold}%.';
$string['teachingheading'] = 'My teaching';
$string['teachinghelp'] = 'Last {$a->days} days. The line marks {$a->threshold}%.';
$string['threshold'] = 'Low attendance threshold (%)';
$string['threshold_desc'] = 'Students whose course overall is below this percentage are counted as low under "My students", and a student\'s own course overall below it is marked Low. Teacher figures are marked against the teacher Present threshold of Zoom attendance instead.';
$string['thresholdmarker'] = 'Threshold: {$a}%';
$string['updated'] = 'Updated {$a}';
$string['zoomattendance:addinstance'] = 'Add a new Zoom attendance block';
$string['zoomattendance:myaddinstance'] = 'Add a new Zoom attendance block to the Dashboard';
