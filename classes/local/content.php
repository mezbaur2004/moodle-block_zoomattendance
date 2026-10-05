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
 * What the block shows a user.
 *
 * @package    block_zoomattendance
 * @copyright  2026 Mezbaur Are Rafi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_zoomattendance\local;

use local_zoomattendance\local\course_summary;
use local_zoomattendance\local\headcount;
use local_zoomattendance\local\settings;
use local_zoomattendance\local\teacher_overview;

/**
 * Collects the current user's block content from local_zoomattendance.
 *
 * Every figure comes from local_zoomattendance's own summaries and capability checks, so the
 * block always agrees with its reports. Sections:
 * - mine: the user's own course overall, per course where they are an expected participant;
 * - students: per course where they view reports, how many students are low (below the Partial
 *   threshold), and out of the expected students how many attended the latest class;
 * - teaching: their own teaching attendance over the recent period, per course;
 * - teachers: the teachers with the lowest attendance, where they view every teacher.
 *
 * The content is cached per user for up to an hour: attendance only changes when the hourly
 * sync runs, and the dashboard is opened on every login.
 */
class content {
    /** @var int Seconds a user's content is kept. */
    public const CACHE_SECS = HOURSECS;
    /** @var int Rows shown per section. */
    public const LIMIT = 5;

    /**
     * The current user's content, from the cache when fresh.
     *
     * @return array See build().
     */
    public static function get(): array {
        global $USER;
        $cache = \cache::make('block_zoomattendance', 'content');
        $entry = $cache->get((int) $USER->id);
        if (is_array($entry) && $entry['time'] > time() - self::CACHE_SECS) {
            return $entry['data'];
        }
        $data = self::build();
        $cache->set((int) $USER->id, ['time' => time(), 'data' => $data]);
        return $data;
    }

    /**
     * Build the current user's content.
     *
     * @return array With mine, students, teaching (lists of rows), teachers ({rows, total} or
     *     null), the from, to and days they cover, thresholds and teacherthresholds (see
     *     student_thresholds()), and built (the time).
     */
    public static function build(): array {
        global $USER;
        $userid = (int) $USER->id;
        $days = self::days();
        $from = usergetmidnight(time() - $days * DAYSECS);
        $to = time();
        $courses = self::zoom_courses($userid);
        $data = [
            'mine' => self::mine($userid, $courses),
            'students' => self::students($userid, $courses),
            'teaching' => [],
            'teachers' => null,
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'thresholds' => self::student_thresholds(),
            'teacherthresholds' => self::teacher_thresholds(),
            'built' => time(),
        ];
        if (settings::teacher_tracking()) {
            $data['teaching'] = self::teaching($userid, $from, $to);
            $data['teachers'] = self::teachers($userid, $from, $to);
        }
        return $data;
    }

    /**
     * Whether a user has nothing to see.
     *
     * @param array $data From build().
     * @return bool
     */
    public static function is_empty(array $data): bool {
        return !$data['mine'] && !$data['students'] && !$data['teaching'] && empty($data['teachers']['rows']);
    }

    /**
     * Period the teacher sections cover, in days.
     *
     * @return int
     */
    public static function days(): int {
        $days = (int) get_config('block_zoomattendance', 'days');
        return $days > 0 ? $days : 30;
    }

    /**
     * Student thresholds: local_zoomattendance's site defaults, which its Course overall uses.
     * Below the Partial threshold a student is low.
     *
     * @return float[] With present and partial.
     */
    public static function student_thresholds(): array {
        $settings = settings::site_defaults();
        return ['present' => (float) $settings->presentpct, 'partial' => (float) $settings->latepct];
    }

    /**
     * Teacher thresholds of local_zoomattendance.
     *
     * @return float[] With present and partial.
     */
    public static function teacher_thresholds(): array {
        $settings = settings::teacher();
        return ['present' => (float) $settings->presentpct, 'partial' => (float) $settings->latepct];
    }

    /**
     * The user's enrolled courses that have a Zoom activity.
     *
     * @param int $userid
     * @return \stdClass[] Course records keyed by id.
     */
    protected static function zoom_courses(int $userid): array {
        global $DB;
        $withzoom = array_flip($DB->get_fieldset_sql('SELECT DISTINCT course FROM {zoom}'));
        $courses = [];
        foreach (enrol_get_users_courses($userid, true) as $course) {
            if (isset($withzoom[$course->id])) {
                $courses[$course->id] = get_course($course->id);
            }
        }
        return $courses;
    }

    /**
     * The user's own course overall, per course where they were expected.
     *
     * @param int $userid
     * @param \stdClass[] $courses
     * @return array[] Each with courseid, name and percentage, in course order.
     */
    protected static function mine(int $userid, array $courses): array {
        $rows = [];
        foreach ($courses as $course) {
            if (!has_capability('local/zoomattendance:viewown', \context_course::instance($course->id))) {
                continue;
            }
            $summary = course_summary::build($course, 0, $userid, 'local/zoomattendance:viewown');
            $percentage = isset($summary->overall[$userid]) ? $summary->overall[$userid]->percentage() : null;
            if ($percentage !== null) {
                $rows[] = ['courseid' => (int) $course->id, 'name' => $course->fullname, 'percentage' => $percentage];
            }
        }
        return $rows;
    }

    /**
     * Per course where the user views reports, the students below the Partial threshold, and how
     * many of the expected students attended its latest class. A user who cannot see all groups of
     * a separate-groups course only counts their own groups.
     *
     * @param int $userid
     * @param \stdClass[] $courses
     * @return array[] Each with courseid, name, low, total and last (the latest class: time, name
     *     and counts from local_zoomattendance's headcount, or null); most low students first.
     */
    protected static function students(int $userid, array $courses): array {
        $threshold = self::student_thresholds()['partial'];
        $rows = [];
        foreach ($courses as $course) {
            $visible = headcount::visible_cells($course);
            if (!$visible) {
                continue;
            }
            $percentages = [];
            foreach ($visible['overall'] as $id => $overall) {
                $percentages[$id] = $overall->percentage();
            }
            $percentages = array_filter($percentages, function ($percentage) {
                return $percentage !== null;
            });
            if (!$percentages) {
                continue;
            }
            $low = count(array_filter($percentages, function ($percentage) use ($threshold) {
                return $percentage < $threshold;
            }));
            $rows[] = [
                'courseid' => (int) $course->id,
                'name' => $course->fullname,
                'low' => $low,
                'total' => count($percentages),
                'last' => self::last_class($visible['summary'], headcount::from_cells($visible['cells'])),
            ];
        }
        usort($rows, function ($a, $b) {
            return ($b['low'] <=> $a['low']) ?: strcmp($a['name'], $b['name']);
        });
        return array_slice($rows, 0, self::LIMIT);
    }

    /**
     * The latest class with expected students, and its headcount.
     *
     * @param course_summary|null $summary
     * @param array[] $counts occurrence id => counts, from headcount.
     * @return array|null With cmid, occurrenceid, time, name (the activity) and counts.
     */
    protected static function last_class(?course_summary $summary, array $counts): ?array {
        $last = null;
        foreach ($summary ? $summary->activities : [] as $activity) {
            foreach ($activity->columns as $occurrenceid => $occurrence) {
                if (empty($counts[$occurrenceid]['expected'])) {
                    continue;
                }
                if (!$last || $occurrence->timestart > $last['time']) {
                    $last = [
                        'cmid' => (int) $activity->cm->id,
                        'occurrenceid' => (int) $occurrenceid,
                        'time' => (int) $occurrence->timestart,
                        'name' => $activity->cm->name,
                        'counts' => $counts[$occurrenceid],
                    ];
                }
            }
        }
        return $last;
    }

    /**
     * The user's own teaching attendance in the period, per course.
     *
     * @param int $userid
     * @param int $from
     * @param int $to
     * @return array[] Each with courseid, name, percentage, classes, joined (the percentage over
     *     only the classes they joined, or null) and joinedclasses.
     */
    protected static function teaching(int $userid, int $from, int $to): array {
        $rows = [];
        foreach (teacher_overview::rows($userid, true, $from, $to) as $row) {
            // The own view also lists the non-editing teachers a coordinator may see.
            if ((int) $row->user->id !== $userid) {
                continue;
            }
            $rows[] = [
                'courseid' => (int) $row->course->id,
                'name' => $row->course->fullname,
                'percentage' => $row->overall ? $row->overall->percentage() : null,
                'classes' => (int) $row->stats['expected'],
                // Over only the classes they joined.
                'joined' => $row->joined ? $row->joined->percentage() : null,
                'joinedclasses' => (int) $row->stats['joined'],
            ];
        }
        return $rows;
    }

    /**
     * The teachers with the lowest attendance in the period, where the user views every teacher.
     *
     * @param int $userid
     * @param int $from
     * @param int $to
     * @return array|null With rows (each with name, courseid, course, percentage and joined) and total,
     *     or null when the user views no teacher reports.
     */
    protected static function teachers(int $userid, int $from, int $to): ?array {
        if (!teacher_overview::has_courses($userid, 'local/zoomattendance:viewteacherreports')) {
            return null;
        }
        $rows = [];
        foreach (teacher_overview::rows($userid, false, $from, $to) as $row) {
            if (!$row->overall || $row->overall->percentage() === null) {
                continue;
            }
            $rows[] = [
                'name' => fullname($row->user),
                'courseid' => (int) $row->course->id,
                'course' => $row->course->fullname,
                'percentage' => $row->overall->percentage(),
                'joined' => $row->joined ? $row->joined->percentage() : null,
            ];
        }
        usort($rows, function ($a, $b) {
            return ($a['percentage'] <=> $b['percentage']) ?: strcmp($a['name'], $b['name']);
        });
        return ['rows' => array_slice($rows, 0, self::LIMIT), 'total' => count($rows)];
    }
}
