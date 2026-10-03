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
 * Renderer for block_zoomattendance.
 *
 * @package    block_zoomattendance
 * @copyright  2026 Mezbaur Are Rafi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_zoomattendance\output;

use html_writer;
use moodle_url;

/**
 * Renders the block's sections.
 */
class renderer extends \plugin_renderer_base {
    /**
     * The whole block content.
     *
     * @param array $data From content::build().
     * @return string
     */
    public function overview(array $data): string {
        global $USER;
        $out = '';
        if ($data['mine']) {
            $items = [];
            foreach ($data['mine'] as $row) {
                $items[] = $this->item(
                    new moodle_url('/local/zoomattendance/user.php', ['course' => $row['courseid'], 'user' => $USER->id]),
                    $this->course_name($row['courseid'], $row['name']),
                    $this->percentage($row['percentage'], $data['threshold'])
                );
            }
            $out .= $this->section(get_string('mine', 'block_zoomattendance'), $items);
        }
        if ($data['students']) {
            $items = [];
            foreach ($data['students'] as $row) {
                $items[] = $this->item(
                    new moodle_url('/local/zoomattendance/course.php', ['id' => $row['courseid']]),
                    $this->course_name($row['courseid'], $row['name']),
                    html_writer::span(
                        get_string('lowcount', 'block_zoomattendance', (object) ['low' => $row['low'], 'total' => $row['total']]),
                        $row['low'] ? 'badge bg-warning badge-warning text-dark' : 'badge bg-light badge-light text-dark'
                    )
                );
            }
            $out .= $this->section(
                get_string('students', 'block_zoomattendance', format_float($data['threshold'], 0)),
                $items
            );
        }
        $range = ['fromts' => $data['from'], 'tots' => usergetmidnight($data['to'])];
        if ($data['teaching']) {
            $items = [];
            foreach ($data['teaching'] as $row) {
                $items[] = $this->item(
                    new moodle_url('/local/zoomattendance/teachers.php', ['id' => $row['courseid']] + $range),
                    $this->course_name($row['courseid'], $row['name']),
                    $this->percentage($row['percentage'], null)
                );
            }
            $out .= $this->section(get_string('teaching', 'block_zoomattendance', $data['days']), $items);
        }
        if (!empty($data['teachers']['rows'])) {
            $items = [];
            foreach ($data['teachers']['rows'] as $row) {
                $items[] = $this->item(
                    new moodle_url('/local/zoomattendance/teachers.php', ['id' => $row['courseid']] + $range),
                    s($row['name']) . html_writer::div($this->course_name($row['courseid'], $row['course']), 'small text-muted'),
                    $this->percentage($row['percentage'], null)
                );
            }
            $all = html_writer::link(
                new moodle_url('/local/zoomattendance/teachersoverview.php', $range),
                get_string('allteachers', 'block_zoomattendance', $data['teachers']['total'])
            );
            $out .= $this->section(get_string('teachers', 'block_zoomattendance', $data['days']), $items, $all);
        }
        return $out;
    }

    /**
     * A titled list.
     *
     * @param string $title
     * @param string[] $items Rendered list items.
     * @param string $footer Optional link under the list.
     * @return string
     */
    protected function section(string $title, array $items, string $footer = ''): string {
        return html_writer::div(
            html_writer::tag('h6', s($title), ['class' => 'mb-1'])
                . html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled mb-1'])
                . ($footer === '' ? '' : html_writer::div($footer, 'small')),
            'block_zoomattendance-section mb-3'
        );
    }

    /**
     * One row: a linked label and a value on the right.
     *
     * @param moodle_url $url
     * @param string $label HTML.
     * @param string $value HTML.
     * @return string
     */
    protected function item(moodle_url $url, string $label, string $value): string {
        return html_writer::tag(
            'li',
            html_writer::link($url, $label, ['class' => 'me-2 mr-2']) . html_writer::span($value, 'text-nowrap'),
            ['class' => 'd-flex justify-content-between align-items-start py-1 border-bottom']
        );
    }

    /**
     * A course name as the course formats it.
     *
     * @param int $courseid
     * @param string $name
     * @return string
     */
    protected function course_name(int $courseid, string $name): string {
        return format_string($name, true, ['context' => \context_course::instance($courseid)]);
    }

    /**
     * A percentage, highlighted when below the threshold.
     *
     * @param float|null $percentage
     * @param float|null $threshold
     * @return string
     */
    protected function percentage(?float $percentage, ?float $threshold): string {
        if ($percentage === null) {
            return '–';
        }
        $text = format_float($percentage, 1) . '%';
        return ($threshold !== null && $percentage < $threshold)
            ? html_writer::tag('strong', $text, ['class' => 'text-danger'])
            : html_writer::tag('strong', $text);
    }
}
