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
 * Moodle app view of the block.
 *
 * @package    block_zoomattendance
 * @copyright  2026 Mezbaur Are Rafi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_zoomattendance\output;

use block_zoomattendance\local\content;
use local_zoomattendance\local\headcount;

/**
 * The block for the Moodle app (see db/mobile.php): the web block's sections as plain rows.
 *
 * All text goes to the app as data and is shown with Angular interpolation, never as markup.
 */
class mobile {
    /**
     * The block.
     *
     * @param array $args
     * @return array
     */
    public static function mobile_block_view(array $args): array {
        $sections = isloggedin() && !isguestuser() ? self::sections(content::get()) : [];
        return [
            'templates' => [['id' => 'main', 'html' => self::template()]],
            'javascript' => '',
            'otherdata' => ['sections' => json_encode($sections)],
        ];
    }

    /**
     * The block's sections as rows.
     *
     * @param array $data From content::build().
     * @return array[] Each with title, hint and rows (each with name, sub, value and color).
     */
    public static function sections(array $data): array {
        $students = $data['thresholds'];
        $teachers = $data['teacherthresholds'];
        $bands = function (array $thresholds): \stdClass {
            return (object) [
                'present' => format_float($thresholds['present'], 0),
                'partial' => format_float($thresholds['partial'], 0),
            ];
        };
        $sections = [];

        if ($data['mine']) {
            $rows = [];
            foreach ($data['mine'] as $row) {
                $rows[] = self::row(self::course_name($row['courseid'], $row['name']), '', $row['percentage'], $students);
            }
            $sections[] = self::section('mine', get_string('minehelp', 'block_zoomattendance', $bands($students)), $rows);
        }

        if ($data['students']) {
            $rows = [];
            foreach ($data['students'] as $row) {
                $sub = '';
                if (!empty($row['last'])) {
                    $a = headcount::string_data($row['last']['counts']);
                    $sub = get_string('lastclass', 'block_zoomattendance', (object) [
                        'name' => format_string($row['last']['name']),
                        'date' => userdate($row['last']['time'], get_string('strftimedatetimeshort', 'langconfig')),
                    ]) . ': ' . get_string('headcount_present', 'local_zoomattendance', $a)
                        . ' · ' . get_string('headcount_rest', 'local_zoomattendance', $a);
                }
                $rows[] = [
                    'name' => self::course_name($row['courseid'], $row['name']),
                    'sub' => $sub,
                    'value' => $row['low'] ? get_string('lowcount', 'block_zoomattendance', (object) $row)
                        : get_string('allabove', 'block_zoomattendance', $row['total']),
                    'color' => $row['low'] ? 'warning' : 'medium',
                ];
            }
            $sections[] = self::section(
                'studentsheading',
                get_string('studentshelp', 'block_zoomattendance', format_float($students['partial'], 0)),
                $rows
            );
        }

        if ($data['teaching']) {
            $rows = [];
            foreach ($data['teaching'] as $row) {
                $sub = get_string('classescount', 'block_zoomattendance', $row['classes']);
                if ($row['joined'] !== null) {
                    $sub .= ' · ' . get_string('whenjoinedvalue', 'block_zoomattendance', format_float($row['joined'], 1) . '%');
                }
                $name = self::course_name($row['courseid'], $row['name']);
                $rows[] = self::row($name, $sub, $row['percentage'], $teachers);
            }
            $a = (object) (['days' => $data['days']] + (array) $bands($teachers));
            $hint = get_string('teachinghelp', 'block_zoomattendance', $a);
            $sections[] = self::section('teachingheading', $hint, $rows);
        }

        if (!empty($data['teachers']['rows'])) {
            $rows = [];
            foreach ($data['teachers']['rows'] as $row) {
                $course = self::course_name($row['courseid'], $row['course']);
                $rows[] = self::row($row['name'], $course, $row['percentage'], $teachers);
            }
            $hint = content::teachers_hint($data['days'], (array) $bands($teachers));
            $sections[] = self::section('teachersheading', $hint, $rows);
        }
        return $sections;
    }

    /**
     * A section.
     *
     * @param string $title String identifier of the title.
     * @param string $hint
     * @param array[] $rows
     * @return array
     */
    protected static function section(string $title, string $hint, array $rows): array {
        return ['title' => get_string($title, 'block_zoomattendance'), 'hint' => $hint, 'rows' => $rows];
    }

    /**
     * A row with a percentage, coloured as on the web.
     *
     * @param string $name
     * @param string $sub
     * @param float|null $percentage
     * @param float[] $thresholds
     * @return array
     */
    protected static function row(string $name, string $sub, ?float $percentage, array $thresholds): array {
        // Bootstrap and Ionic share these colour names.
        return [
            'name' => $name,
            'sub' => $sub,
            'value' => $percentage === null ? '–' : format_float($percentage, 1) . '%',
            'color' => renderer::variant($percentage, $thresholds),
        ];
    }

    /**
     * A course name as plain text.
     *
     * @param int $courseid
     * @param string $name
     * @return string
     */
    protected static function course_name(int $courseid, string $name): string {
        return format_string($name, true, ['context' => \context_course::instance($courseid), 'escape' => false]);
    }

    /**
     * The template: data only, through Angular interpolation.
     *
     * @return string
     */
    protected static function template(): string {
        return <<<'HTML'
<ion-list>
    <ng-container *ngFor="let section of CONTENT_OTHERDATA.sections">
        <ion-item-divider>
            <ion-label><h2>{{ section.title }}</h2><p>{{ section.hint }}</p></ion-label>
        </ion-item-divider>
        <ion-item *ngFor="let row of section.rows">
            <ion-label><h3>{{ row.name }}</h3><p *ngIf="row.sub">{{ row.sub }}</p></ion-label>
            <ion-badge slot="end" [color]="row.color">{{ row.value }}</ion-badge>
        </ion-item>
    </ng-container>
</ion-list>
HTML;
    }
}
