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
 * Tests for the block content.
 *
 * @package    block_zoomattendance
 * @copyright  2026 Mezbaur Are Rafi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_zoomattendance\local;

use local_zoomattendance\local\sync;

#[\PHPUnit\Framework\Attributes\CoversClass(content::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\block_zoomattendance\output\renderer::class)]
/**
 * Tests for the block content, built on local_zoomattendance's generator.
 *
 * @covers \block_zoomattendance\local\content
 * @covers \block_zoomattendance\output\renderer
 */
final class content_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass[] */
    protected $users = [];

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        set_config('defaultenabled', 1, 'local_zoomattendance');
        set_config('teachertracking', 1, 'local_zoomattendance');
        set_config('teachertrackingsince', 1, 'local_zoomattendance');
        $dg = $this->getDataGenerator();
        $generator = $dg->get_plugin_generator('local_zoomattendance');
        $this->course = $dg->create_course(['fullname' => 'Spoken English']);
        $this->users = [
            'full' => $dg->create_and_enrol($this->course, 'student', ['firstname' => 'Full']),
            'low' => $dg->create_and_enrol($this->course, 'student', ['firstname' => 'Low']),
            'coordinator' => $dg->create_and_enrol($this->course, 'editingteacher', ['firstname' => 'Coordinator']),
            'teacher' => $dg->create_and_enrol($this->course, 'teacher', ['firstname' => 'Teacher']),
            'manager' => $dg->create_user(['firstname' => 'Manager']),
            'outsider' => $dg->create_user(),
        ];
        role_assign(
            $DB->get_field('role', 'id', ['shortname' => 'manager']),
            $this->users['manager']->id,
            \context_system::instance()->id
        );

        // One past one-hour class: Full and the coordinator stay throughout, Low for 15 minutes,
        // the non-editing teacher never joins.
        $start = time() - DAYSECS;
        $cm = $generator->create_zoom(['course' => $this->course->id, 'start_time' => $start, 'duration' => HOURSECS]);
        $session = $generator->create_session($cm, $start, $start + HOURSECS);
        foreach (['full' => HOURSECS, 'low' => 15 * MINSECS, 'coordinator' => HOURSECS] as $key => $secs) {
            $generator->create_participant($session, $start, $start + $secs, ['userid' => $this->users[$key]->id]);
        }
        sync::sync_all();
    }

    public function test_student_sees_own_course_overall(): void {
        $this->setUser($this->users['full']);
        $data = content::build();
        $this->assertCount(1, $data['mine']);
        $this->assertSame('Spoken English', $data['mine'][0]['name']);
        $this->assertEqualsWithDelta(100.0, $data['mine'][0]['percentage'], 0.01);
        $this->assertSame([], $data['students']);
        $this->assertSame([], $data['teaching']);
        $this->assertNull($data['teachers']);

        $this->setUser($this->users['low']);
        $this->assertEqualsWithDelta(25.0, content::build()['mine'][0]['percentage'], 0.01);
    }

    public function test_coordinator_sees_low_students_and_own_teaching(): void {
        $this->setUser($this->users['coordinator']);
        $data = content::build();
        $this->assertSame([], $data['mine']);
        $row = $data['students'][0];
        $expected = ['courseid' => (int) $this->course->id, 'name' => 'Spoken English', 'low' => 1, 'total' => 2];
        $this->assertSame($expected, array_diff_key($row, ['last' => 1]));
        // The latest class: out of 2 expected students, Full was present and Low absent.
        $this->assertSame(['expected' => 2, 'overall' => 1, 'present' => 1, 'partial' => 0, 'absent' => 1], $row['last']['counts']);
        $this->assertSame('Zoom', substr($row['last']['name'], 0, 4));
        $this->assertCount(1, $data['teaching']);
        $this->assertEqualsWithDelta(100.0, $data['teaching'][0]['percentage'], 0.01);
        $this->assertSame(1, $data['teaching'][0]['classes']);
        $this->assertEqualsWithDelta(100.0, $data['teaching'][0]['joined'], 0.01);
        $this->assertSame(1, $data['teaching'][0]['joinedclasses']);
        // Coordinators see the non-editing teachers of their own courses, never coordinators.
        $this->assertSame(1, $data['teachers']['total']);
        $this->assertSame(fullname($this->users['teacher']), $data['teachers']['rows'][0]['name']);
        $this->assertTrue($data['teachers']['mine']);

        // Low means below the Partial threshold of Zoom attendance (50 % by default; Low has 25 %).
        set_config('latepct', 20, 'local_zoomattendance');
        $this->assertSame(0, content::build()['students'][0]['low']);
    }

    public function test_teacher_in_separate_groups_counts_own_groups_only(): void {
        global $DB;
        $dg = $this->getDataGenerator();
        $DB->update_record('course', (object) ['id' => $this->course->id, 'groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $group = $dg->create_group(['courseid' => $this->course->id]);
        $dg->create_group_member(['groupid' => $group->id, 'userid' => $this->users['teacher']->id]);
        $dg->create_group_member(['groupid' => $group->id, 'userid' => $this->users['low']->id]);
        rebuild_course_cache($this->course->id, true);

        $this->setUser($this->users['teacher']);
        $data = content::build();
        $this->assertSame(1, $data['students'][0]['total']);
        $this->assertSame(1, $data['students'][0]['low']);
        $counts = ['expected' => 1, 'overall' => 0, 'present' => 0, 'partial' => 0, 'absent' => 1];
        $this->assertSame($counts, $data['students'][0]['last']['counts']);
        // Absent from the only class, so nothing for "When joined".
        $this->assertEqualsWithDelta(0.0, $data['teaching'][0]['percentage'], 0.01);
        $this->assertNull($data['teaching'][0]['joined']);
        $this->assertSame(0, $data['teaching'][0]['joinedclasses']);
    }

    public function test_manager_sees_lowest_teachers_first(): void {
        $this->setUser($this->users['manager']);
        $data = content::build();
        // Non-editing teachers only: the coordinator is left out.
        $this->assertSame(1, $data['teachers']['total']);
        $this->assertSame(fullname($this->users['teacher']), $data['teachers']['rows'][0]['name']);
        $this->assertEqualsWithDelta(0.0, $data['teachers']['rows'][0]['percentage'], 0.01);
        $this->assertFalse($data['teachers']['mine']);
        $this->assertFalse(content::is_empty($data));

        // Without teacher tracking there are no teacher sections.
        set_config('teachertracking', 0, 'local_zoomattendance');
        $this->assertNull(content::build()['teachers']);
    }

    public function test_renders_every_section(): void {
        global $PAGE;
        $this->setUser($this->users['manager']);
        $data = content::build();
        $data['mine'] = [['courseid' => (int) $this->course->id, 'name' => 'Spoken English', 'percentage' => 40.0]];
        // The manager is not enrolled: take the students and teaching sections from the coordinator.
        $this->setUser($this->users['coordinator']);
        $coordinator = content::build();
        $data['students'] = $coordinator['students'];
        $data['teaching'] = $coordinator['teaching'];
        $html = $PAGE->get_renderer('block_zoomattendance')->overview($data);
        $this->assertStringContainsString('My attendance', $html);
        $this->assertStringContainsString('Course overall. Green from 75%, orange from 50%, red below.', $html);
        $this->assertStringContainsString('40.0%', $html);
        // 40 % is below the Partial threshold (50 %): red, and labelled Low.
        $this->assertStringContainsString('text-danger', $html);
        $this->assertStringContainsString('>Low<', $html);
        $this->assertStringContainsString('block_zoomattendance-fill bg-danger" style="width: 40%;"', $html);
        // The line marks the student Present threshold (75 %) and the teacher one (90 %).
        $this->assertStringContainsString('left: 75%;', $html);
        $this->assertStringContainsString('Teacher attendance', $html);
        $hint = 'Non-editing teachers, last 30 days, lowest first. Green from 90%, orange from 10%, red below.';
        $this->assertStringContainsString($hint, $html);
        $this->assertStringContainsString('left: 90%;', $html);
        // The coordinator's own teaching at 100 % is green, and they joined their one class.
        $this->assertStringContainsString('bg-success" style="width: 100%;"', $html);
        $this->assertStringContainsString('When joined: 100.0%', $html);
        $this->assertStringContainsString('All non-editing teachers (1)', $html);
        $this->assertStringNotContainsString('mine=1', $html);
        // The latest class: present of expected, with a bar split by status.
        $this->assertStringContainsString('Last class: ', $html);
        $this->assertStringContainsString('1 of 2 present', $html);
        $this->assertStringContainsString('1 present + 0 partial · 1 absent', $html);
        $this->assertStringContainsString('block_zoomattendance-split', $html);
        $this->assertStringContainsString('/local/zoomattendance/report.php?id=', $html);
        $this->assertStringContainsString('/local/zoomattendance/teachersoverview.php', $html);
        $this->assertStringContainsString('Updated', $PAGE->get_renderer('block_zoomattendance')->updated($data['built']));
    }

    public function test_nothing_to_show_and_cache(): void {
        $this->setUser($this->users['outsider']);
        $this->assertTrue(content::is_empty(content::build()));

        // The content is cached per user until it is an hour old.
        $this->setUser($this->users['full']);
        $first = content::get();
        set_config('latepct', 10, 'local_zoomattendance');
        $this->assertSame($first, content::get());
        $cache = \cache::make('block_zoomattendance', 'content');
        $cache->set((int) $this->users['full']->id, ['time' => time() - content::CACHE_SECS - 1, 'data' => $first]);
        $this->assertSame(10.0, content::get()['thresholds']['partial']);
    }

    public function test_colours_match_zoom_attendance(): void {
        $renderer = \block_zoomattendance\output\renderer::class;
        $students = content::student_thresholds();
        $this->assertSame(['present' => 75.0, 'partial' => 50.0], $students);
        $this->assertSame('success', $renderer::variant(75.0, $students));
        $this->assertSame('warning', $renderer::variant(74.9, $students));
        $this->assertSame('warning', $renderer::variant(50.0, $students));
        $this->assertSame('danger', $renderer::variant(49.9, $students));
        $teachers = content::teacher_thresholds();
        $this->assertSame(['present' => 90.0, 'partial' => 10.0], $teachers);
        $this->assertSame('warning', $renderer::variant(65.8, $teachers));
        $this->assertSame('danger', $renderer::variant(5.0, $teachers));
    }
}
