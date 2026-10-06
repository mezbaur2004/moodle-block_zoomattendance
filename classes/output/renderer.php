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

use block_zoomattendance\local\content;
use html_writer;
use moodle_url;

/**
 * Renders the block's sections.
 *
 * Each percentage is a number plus a thin bar coloured as local_zoomattendance colours it: green
 * from the Present threshold, orange from the Partial threshold and red below, with a line at the
 * Present threshold. A red value also gets a "Low" label, so it never relies on colour alone.
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
        $students = $data['thresholds'];
        // Teachers are measured against their own, stricter thresholds.
        $teachers = $data['teacherthresholds'];
        $range = ['fromts' => $data['from'], 'tots' => usergetmidnight($data['to'])];
        $out = '';

        if ($data['mine']) {
            $items = [];
            foreach ($data['mine'] as $row) {
                $items[] = $this->meter_row(
                    new moodle_url('/local/zoomattendance/user.php', ['course' => $row['courseid'], 'user' => $USER->id]),
                    $this->course_name($row['courseid'], $row['name']),
                    '',
                    $row['percentage'],
                    $students
                );
            }
            $out .= $this->section(
                'i/user',
                get_string('mine', 'block_zoomattendance'),
                get_string('minehelp', 'block_zoomattendance', (object) self::bands($students)),
                $items
            );
        }

        if ($data['students']) {
            $items = [];
            foreach ($data['students'] as $row) {
                $items[] = $this->students_row($row);
            }
            $out .= $this->section(
                'i/users',
                get_string('studentsheading', 'block_zoomattendance'),
                get_string('studentshelp', 'block_zoomattendance', format_float($students['partial'], 0)),
                $items
            );
        }

        if ($data['teaching']) {
            $items = [];
            foreach ($data['teaching'] as $row) {
                $items[] = $this->meter_row(
                    new moodle_url('/local/zoomattendance/teachers.php', ['id' => $row['courseid']] + $range),
                    $this->course_name($row['courseid'], $row['name']),
                    get_string('classescount', 'block_zoomattendance', $row['classes'])
                        . $this->joined_line($row['joined'], $row['joinedclasses'], $row['classes']),
                    $row['percentage'],
                    $teachers
                );
            }
            $out .= $this->section(
                'i/calendar',
                get_string('teachingheading', 'block_zoomattendance'),
                get_string('teachinghelp', 'block_zoomattendance', (object) (['days' => $data['days']] + self::bands($teachers))),
                $items
            );
        }

        if (!empty($data['teachers']['rows'])) {
            $items = [];
            foreach ($data['teachers']['rows'] as $row) {
                $items[] = $this->meter_row(
                    new moodle_url('/local/zoomattendance/teachers.php', ['id' => $row['courseid']] + $range),
                    s($row['name']),
                    $this->course_name($row['courseid'], $row['course']) . $this->joined_line($row['joined']),
                    $row['percentage'],
                    $teachers
                );
            }
            // Coordinators see the list of their own courses. The list has coordinators too, so
            // the link names every role it covers.
            $mine = !empty($data['teachers']['mine']);
            $all = html_writer::link(
                new moodle_url('/local/zoomattendance/teachersoverview.php', $range + ($mine ? ['mine' => 1] : [])),
                s(content::overview_label($mine)),
                ['class' => 'btn btn-sm btn-outline-secondary mt-2']
            );
            $out .= $this->section(
                'i/report',
                get_string('teachersheading', 'block_zoomattendance'),
                content::teachers_hint($data['days'], self::bands($teachers)),
                $items,
                $all
            );
        }
        return $out;
    }

    /**
     * When the content was built, for the block footer.
     *
     * @param int $time
     * @return string
     */
    public function updated(int $time): string {
        return html_writer::div(
            get_string('updated', 'block_zoomattendance', userdate($time, get_string('strftimedatetimeshort', 'langconfig'))),
            'text-muted small',
            ['title' => get_string('refreshes', 'block_zoomattendance')]
        );
    }

    /**
     * A section: icon and title, an optional hint, its rows and an optional footer.
     *
     * @param string $icon Core pix identifier.
     * @param string $title
     * @param string $hint Plain text under the title, or ''.
     * @param string[] $items Rendered rows.
     * @param string $footer HTML, or ''.
     * @return string
     */
    protected function section(string $icon, string $title, string $hint, array $items, string $footer = ''): string {
        $heading = html_writer::tag(
            'h6',
            $this->output->pix_icon($icon, '', 'moodle', ['class' => 'icon']) . s($title),
            ['class' => 'block_zoomattendance-title mb-0']
        );
        return html_writer::div(
            $heading
                . ($hint === '' ? '' : html_writer::div(s($hint), 'text-muted small'))
                . html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled mb-0 mt-2'])
                . $footer,
            'block_zoomattendance-section'
        );
    }

    /**
     * "When joined" under a teacher figure: attendance over only the classes they joined.
     *
     * @param float|null $joined
     * @param int|null $classesjoined How many classes they joined, to show beside it.
     * @param int|null $classes How many classes counted.
     * @return string '' when they joined none.
     */
    protected function joined_line(?float $joined, ?int $classesjoined = null, ?int $classes = null): string {
        if ($joined === null) {
            return '';
        }
        $text = get_string('whenjoinedvalue', 'block_zoomattendance', format_float($joined, 1) . '%');
        if ($classesjoined !== null && $classes !== null) {
            $text .= ' ' . get_string('joinedof', 'block_zoomattendance', (object) [
                'joined' => $classesjoined,
                'classes' => $classes,
            ]);
        }
        return html_writer::div(s($text), 'block_zoomattendance-joined', [
            'title' => get_string('whenjoined_help', 'block_zoomattendance'),
        ]);
    }

    /**
     * Thresholds as text for the section hints.
     *
     * @param float[] $thresholds With present and partial.
     * @return string[] With present and partial.
     */
    protected static function bands(array $thresholds): array {
        return [
            'present' => format_float($thresholds['present'], 0),
            'partial' => format_float($thresholds['partial'], 0),
        ];
    }

    /**
     * Colour of a percentage, as local_zoomattendance colours overall figures.
     *
     * @param float|null $percentage
     * @param float[] $thresholds With present and partial.
     * @return string A Bootstrap colour: success, warning or danger.
     */
    public static function variant(?float $percentage, array $thresholds): string {
        if ($percentage !== null && $percentage >= $thresholds['present']) {
            return 'success';
        }
        return ($percentage !== null && $percentage >= $thresholds['partial']) ? 'warning' : 'danger';
    }

    /**
     * A row with a percentage bar.
     *
     * @param moodle_url $url
     * @param string $label HTML.
     * @param string $sublabel HTML under the label, or ''.
     * @param float|null $percentage
     * @param float[] $thresholds With present and partial.
     * @return string
     */
    protected function meter_row(moodle_url $url, string $label, string $sublabel, ?float $percentage, array $thresholds): string {
        $variant = self::variant($percentage, $thresholds);
        $low = $percentage !== null && $variant === 'danger';
        $value = $percentage === null ? '–' : format_float($percentage, 1) . '%';
        $top = html_writer::div(
            html_writer::div(
                html_writer::link($url, $label, ['class' => 'block_zoomattendance-label'])
                    . ($sublabel === '' ? '' : html_writer::div($sublabel, 'text-muted small')),
                'block_zoomattendance-name'
            )
            . html_writer::div(
                ($low ? $this->low_label() : '')
                    . html_writer::tag('strong', $value, ['class' => $low ? 'text-danger' : '']),
                'block_zoomattendance-value text-nowrap'
            ),
            'd-flex justify-content-between align-items-start'
        );
        $bar = $this->meter($percentage, $thresholds, $variant);
        return html_writer::tag('li', $top . $bar, ['class' => 'block_zoomattendance-row']);
    }

    /**
     * A row with a course's count of students below the Partial threshold.
     *
     * @param array $row With courseid, name, low and total.
     * @return string
     */
    protected function students_row(array $row): string {
        if ($row['low']) {
            $status = html_writer::span(
                $this->output->pix_icon('i/warning', '', 'moodle', ['class' => 'icon'])
                    . get_string('lowcount', 'block_zoomattendance', (object) ['low' => $row['low'], 'total' => $row['total']]),
                'badge bg-warning badge-warning text-dark'
            );
        } else {
            $status = html_writer::span(
                $this->output->pix_icon('i/checkedcircle', '', 'moodle', ['class' => 'icon'])
                    . get_string('allabove', 'block_zoomattendance', $row['total']),
                'badge bg-light badge-light text-dark border'
            );
        }
        $top = html_writer::div(
            html_writer::link(
                new moodle_url('/local/zoomattendance/course.php', ['id' => $row['courseid']]),
                $this->course_name($row['courseid'], $row['name']),
                ['class' => 'block_zoomattendance-label block_zoomattendance-name']
            )
            . html_writer::div($status, 'block_zoomattendance-value text-nowrap'),
            'd-flex justify-content-between align-items-center'
        );
        $last = empty($row['last']) ? '' : $this->last_class($row['last']);
        return html_writer::tag('li', $top . $last, ['class' => 'block_zoomattendance-row']);
    }

    /**
     * Out of the students expected at a course's latest class, how many were present overall
     * (present and partial) and absent: as local_zoomattendance shows it, with a bar split into
     * the three colours.
     *
     * @param array $last With cmid, occurrenceid, time, name and counts.
     * @return string
     */
    protected function last_class(array $last): string {
        $counts = $last['counts'];
        $a = \local_zoomattendance\local\headcount::string_data($counts);
        $segments = '';
        foreach (['present' => 'success', 'partial' => 'warning', 'absent' => 'danger'] as $state => $variant) {
            if ($counts[$state]) {
                $segments .= html_writer::div('', 'bg-' . $variant, [
                    'style' => 'width: ' . round(100 * $counts[$state] / $counts['expected'], 1) . '%;',
                ]);
            }
        }
        $when = html_writer::link(
            new moodle_url('/local/zoomattendance/report.php', ['id' => $last['cmid'], 'occurrence' => $last['occurrenceid']]),
            get_string('lastclass', 'block_zoomattendance', (object) [
                'name' => format_string($last['name']),
                'date' => userdate($last['time'], get_string('strftimedatetimeshort', 'langconfig')),
            ]),
            ['class' => 'text-muted']
        );
        $present = html_writer::tag('strong', s(get_string('headcount_present', 'local_zoomattendance', $a)));
        $rest = html_writer::span(s(get_string('headcount_rest', 'local_zoomattendance', $a)), 'text-muted');
        return html_writer::div(
            html_writer::div($when)
                . html_writer::div($present . ' · ' . $rest)
                . html_writer::div($segments, 'block_zoomattendance-split', ['aria-hidden' => 'true']),
            'block_zoomattendance-lastclass small',
            ['title' => get_string('headcount_full', 'local_zoomattendance', $a)]
        );
    }

    /**
     * The "Low" label, so low values never rely on colour alone.
     *
     * @return string
     */
    protected function low_label(): string {
        return html_writer::span(get_string('low', 'block_zoomattendance'), 'badge bg-danger badge-danger me-1 mr-1');
    }

    /**
     * A thin bar filled to the percentage in its colour, with a line at the Present threshold.
     *
     * @param float|null $percentage
     * @param float[] $thresholds With present and partial.
     * @param string $variant From variant().
     * @return string
     */
    protected function meter(?float $percentage, array $thresholds, string $variant): string {
        $width = $percentage === null ? 0 : max(0, min(100, $percentage));
        $fill = html_writer::div('', 'block_zoomattendance-fill bg-' . $variant, [
            'style' => 'width: ' . round($width, 1) . '%;',
        ]);
        $marker = html_writer::div('', 'block_zoomattendance-marker', [
            'style' => 'left: ' . round($thresholds['present'], 1) . '%;',
            'title' => get_string('thresholdmarker', 'block_zoomattendance', format_float($thresholds['present'], 0)),
        ]);
        return html_writer::div($fill . $marker, 'block_zoomattendance-meter', [
            'role' => 'progressbar',
            'aria-valuemin' => 0,
            'aria-valuemax' => 100,
            'aria-valuenow' => round($width, 1),
            'aria-label' => $percentage === null ? '–' : format_float($percentage, 1) . '%',
        ]);
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
}
