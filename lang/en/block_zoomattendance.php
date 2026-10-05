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
$string['allteachers'] = 'All non-editing teachers ({$a}) ›';
$string['cachedef_content'] = 'Each user\'s Zoom attendance block content';
$string['classescount'] = 'Classes: {$a}';
$string['colours_desc'] = 'Bars are coloured as in Zoom attendance: green from the Present threshold, orange from the Partial threshold and red below, with a line at the Present threshold. Student figures use its site defaults and teacher figures its teacher thresholds, set under Site administration > Plugins > Local plugins > Zoom attendance. Students below the Partial threshold are counted as low.';
$string['days'] = 'Teacher period (days)';
$string['days_desc'] = 'The teacher sections cover classes from this many days ago until today.';
$string['joinedof'] = '({$a->joined} of {$a->classes} joined)';
$string['lastclass'] = 'Last class: {$a->name}, {$a->date}';
$string['low'] = 'Low';
$string['lowcount'] = '{$a->low} of {$a->total} low';
$string['mine'] = 'My attendance';
$string['minehelp'] = 'Course overall. Green from {$a->present}%, orange from {$a->partial}%, red below.';
$string['nothing'] = 'Nothing to show: this account has no Zoom attendance yet.';
$string['pluginname'] = 'Zoom attendance';
$string['privacy:metadata'] = 'The Zoom attendance block stores no personal data. It shows data from the Zoom attendance local plugin.';
$string['refreshes'] = 'Figures are refreshed at most an hour after they change.';
$string['studentsheading'] = 'My students';
$string['studentshelp'] = 'Students under {$a}% course overall, and who attended the latest class.';
$string['teachersheading'] = 'Teacher attendance';
$string['teachershelp'] = 'Non-editing teachers, last {$a->days} days, lowest first. Green from {$a->present}%, orange from {$a->partial}%, red below.';
$string['teachingheading'] = 'My teaching';
$string['teachinghelp'] = 'Last {$a->days} days. Green from {$a->present}%, orange from {$a->partial}%, red below.';
$string['thresholdmarker'] = 'The line marks the Present threshold, {$a}%.';
$string['updated'] = 'Updated {$a}';
$string['whenjoined_help'] = 'Attendance over only the classes the teacher joined: classes they missed or that were not held are left out.';
$string['whenjoinedvalue'] = 'When joined: {$a}';
$string['zoomattendance:addinstance'] = 'Add a new Zoom attendance block';
$string['zoomattendance:myaddinstance'] = 'Add a new Zoom attendance block to the Dashboard';
