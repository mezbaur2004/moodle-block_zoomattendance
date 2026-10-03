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
/**
 * Tests for the block content, built on local_zoomattendance's generator.
 *
 * @covers \block_zoomattendance\local\content
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
        $expected = [['courseid' => (int) $this->course->id, 'name' => 'Spoken English', 'low' => 1, 'total' => 2]];
        $this->assertSame($expected, $data['students']);
        $this->assertCount(1, $data['teaching']);
        $this->assertEqualsWithDelta(100.0, $data['teaching'][0]['percentage'], 0.01);
        $this->assertSame(1, $data['teaching'][0]['classes']);
        // Coordinators see their Teachers on the reports, not in the managers' section.
        $this->assertNull($data['teachers']);

        // The threshold is a setting.
        set_config('threshold', 20, 'block_zoomattendance');
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
        // Absent from the only class.
        $this->assertEqualsWithDelta(0.0, $data['teaching'][0]['percentage'], 0.01);
    }

    public function test_manager_sees_lowest_teachers_first(): void {
        $this->setUser($this->users['manager']);
        $data = content::build();
        $this->assertSame(2, $data['teachers']['total']);
        $this->assertSame(fullname($this->users['teacher']), $data['teachers']['rows'][0]['name']);
        $this->assertEqualsWithDelta(0.0, $data['teachers']['rows'][0]['percentage'], 0.01);
        $this->assertSame(fullname($this->users['coordinator']), $data['teachers']['rows'][1]['name']);
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
        $html = $PAGE->get_renderer('block_zoomattendance')->overview($data);
        $this->assertStringContainsString('My attendance', $html);
        $this->assertStringContainsString('40.0%', $html);
        $this->assertStringContainsString('text-danger', $html);
        $this->assertStringContainsString('Lowest teacher attendance (last 30 days)', $html);
        $this->assertStringContainsString('All teachers (2)', $html);
        $this->assertStringContainsString('/local/zoomattendance/teachersoverview.php', $html);
    }

    public function test_nothing_to_show_and_cache(): void {
        $this->setUser($this->users['outsider']);
        $this->assertTrue(content::is_empty(content::build()));

        // The content is cached per user until it is an hour old.
        $this->setUser($this->users['full']);
        $first = content::get();
        set_config('threshold', 10, 'block_zoomattendance');
        $this->assertSame($first, content::get());
        $cache = \cache::make('block_zoomattendance', 'content');
        $cache->set((int) $this->users['full']->id, ['time' => time() - content::CACHE_SECS - 1, 'data' => $first]);
        $this->assertSame(10.0, content::get()['threshold']);
    }
}
