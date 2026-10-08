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
        $this->assertSame([], $data['recent']);
        $this->assertSame([], $data['teaching']);
        $this->assertNull($data['teachers']);

        $this->setUser($this->users['low']);
        $this->assertEqualsWithDelta(25.0, content::build()['mine'][0]['percentage'], 0.01);
    }

    public function test_coordinator_sees_low_students_and_own_teaching(): void {
        $this->setUser($this->users['coordinator']);
        $data = content::build();
        $this->assertSame([], $data['mine']);
        $expected = ['courseid' => (int) $this->course->id, 'name' => 'Spoken English', 'low' => 1, 'total' => 2];
        $this->assertSame($expected, $data['students'][0]);
        // The class: out of 2 expected students, Full was present and Low absent.
        $this->assertCount(1, $data['recent']);
        $class = $data['recent'][0];
        $this->assertSame(['expected' => 2, 'overall' => 1, 'present' => 1, 'partial' => 0, 'absent' => 1], $class['counts']);
        $this->assertEqualsWithDelta(50.0, $class['percentage'], 0.01);
        $this->assertSame('Zoom', substr($class['name'], 0, 4));
        $this->assertSame('Spoken English', $class['course']);
        $this->assertSame((int) $this->course->id, $class['courseid']);
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

    public function test_recent_classes_newest_first_across_courses(): void {
        $dg = $this->getDataGenerator();
        $generator = $dg->get_plugin_generator('local_zoomattendance');
        $history = $dg->create_course(['fullname' => 'History']);
        $maths = $dg->create_course(['fullname' => 'Maths']);
        $historian = $dg->create_and_enrol($history, 'editingteacher');
        $students = [
            $history->id => $dg->create_and_enrol($history, 'student'),
            $maths->id => $dg->create_and_enrol($maths, 'student'),
        ];
        // Name => course, hours ago, whether the student stays throughout (else 5 minutes).
        $classes = [
            'H3' => [$history, 3, true],
            'M4' => [$maths, 4, false],
            'H5' => [$history, 5, false],
            'M6' => [$maths, 6, true],
            'H30' => [$history, 30, true],
            // Before the 30-day period.
            'M960' => [$maths, 960, true],
        ];
        foreach ($classes as $name => [$course, $hours, $stays]) {
            $start = time() - $hours * HOURSECS;
            $record = ['course' => $course->id, 'name' => $name, 'start_time' => $start, 'duration' => HOURSECS];
            $cm = $generator->create_zoom($record);
            $session = $generator->create_session($cm, $start, $start + HOURSECS);
            $leave = $start + ($stays ? HOURSECS : 5 * MINSECS);
            $generator->create_participant($session, $start, $leave, ['userid' => $students[$course->id]->id]);
        }
        sync::sync_all();

        // Managers: the five latest classes of every course, Spoken English's from yesterday included.
        $this->setUser($this->users['manager']);
        $recent = content::build()['recent'];
        $this->assertSame(['H3', 'M4', 'H5', 'M6'], array_slice(array_column($recent, 'name'), 0, 4));
        $this->assertSame('Spoken English', $recent[4]['course']);
        $this->assertSame([100.0, 0.0, 0.0, 100.0], array_slice(array_column($recent, 'percentage'), 0, 4));
        $this->assertSame(['History', 'Maths'], array_slice(array_column($recent, 'course'), 0, 2));

        // Teachers: only the classes of their own courses, older ones too while in the period.
        $this->setUser($historian);
        $this->assertSame(['H3', 'H5', 'H30'], array_column(content::build()['recent'], 'name'));
        $this->setUser($this->users['coordinator']);
        $this->assertSame(['Spoken English'], array_column(content::build()['recent'], 'course'));
    }

    public function test_teacher_in_separate_groups_counts_own_groups_only(): void {
        global $DB;
        $dg = $this->getDataGenerator();
        $DB->update_record('course', (object) ['id' => $this->course->id, 'groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $group = $dg->create_group(['courseid' => $this->course->id]);
        $dg->create_group_member(['groupid' => $group->id, 'userid' => $this->users['teacher']->id]);
        $dg->create_group_member(['groupid' => $group->id, 'userid' => $this->users['low']->id]);
        rebuild_course_cache($this->course->id, true);
        // Past classes count the groups users were in then: freeze them again with these groups.
        $DB->delete_records('local_zoomattendance_roster');
        $DB->set_field('local_zoomattendance_occ', 'rosterfrozen', 0);
        sync::sync_all();

        $this->setUser($this->users['teacher']);
        $data = content::build();
        $this->assertSame(1, $data['students'][0]['total']);
        $this->assertSame(1, $data['students'][0]['low']);
        $counts = ['expected' => 1, 'overall' => 0, 'present' => 0, 'partial' => 0, 'absent' => 1];
        $this->assertSame($counts, $data['recent'][0]['counts']);
        $this->assertEqualsWithDelta(0.0, $data['recent'][0]['percentage'], 0.01);
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
        // Managers see the classes of every course, though not enrolled.
        $this->assertCount(1, $data['recent']);
        $this->assertSame(2, $data['recent'][0]['counts']['expected']);

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
        $hint = 'Role: Non-editing teacher. Last 30 days, lowest first. Green from 90%, orange from 10%, red below.';
        $this->assertStringContainsString($hint, $html);
        $this->assertStringContainsString('left: 90%;', $html);
        // The coordinator's own teaching at 100 % is green, and they joined their one class.
        $this->assertStringContainsString('bg-success" style="width: 100%;"', $html);
        $this->assertStringContainsString('When joined: 100.0%', $html);
        // The overview lists coordinators too, and the link says so.
        $this->assertStringContainsString('Every Non-editing teacher and Teacher ›', $html);
        $this->assertStringNotContainsString('mine=1', $html);
        // My students has only the count of low students.
        $this->assertStringContainsString('Students under 50% course overall.', $html);
        $this->assertStringContainsString('1 of 2 low', $html);
        // Recent classes: present of expected, coloured by the student thresholds, linked to the class.
        $this->assertStringContainsString('Recent classes', $html);
        $hint = 'Last 30 days, newest first. Students present (present + partial) out of those expected. Green from 75%, '
            . 'orange from 50%, red below.';
        $this->assertStringContainsString($hint, $html);
        $this->assertStringContainsString('Spoken English · ', $html);
        $full = 'Out of 2 expected students: 1 present (1 present + 0 partial), 1 absent.';
        $this->assertStringContainsString('<div title="' . $full . '"><strong>1 of 2 present</strong></div>', $html);
        $this->assertStringContainsString('block_zoomattendance-fill bg-warning" style="width: 50%;"', $html);
        $class = $data['recent'][0];
        $url = '/local/zoomattendance/report.php?id=' . $class['cmid'] . '&amp;occurrence=' . $class['occurrenceid'];
        $this->assertStringContainsString($url, $html);
        $this->assertStringContainsString('/local/zoomattendance/teachersoverview.php', $html);
        $this->assertStringContainsString('Updated', $PAGE->get_renderer('block_zoomattendance')->updated($data['built']));
    }

    public function test_uses_the_site_role_names(): void {
        global $DB, $PAGE;
        // The site renames its roles: non-editing teachers are Teachers, teachers Coordinators.
        $DB->set_field('role', 'name', 'Teacher', ['shortname' => 'teacher']);
        $DB->set_field('role', 'name', 'Coordinator', ['shortname' => 'editingteacher']);
        $this->assertSame(['Teacher'], content::teacher_roles(false));
        $this->assertSame(['Coordinator'], content::teacher_roles(true));
        $this->assertSame(['Teacher', 'Coordinator'], content::teacher_roles());

        $this->setUser($this->users['manager']);
        $html = $PAGE->get_renderer('block_zoomattendance')->overview(content::build());
        $this->assertStringContainsString('Role: Teacher. Last 30 days, lowest first.', $html);
        $this->assertStringContainsString('Every Teacher and Coordinator ›', $html);
        $this->assertStringNotContainsString('editing', $html);

        // A coordinator's link opens their own view: their teaching and their courses' teachers.
        $this->setUser($this->users['coordinator']);
        $html = $PAGE->get_renderer('block_zoomattendance')->overview(content::build());
        $this->assertStringContainsString('My teaching and every Teacher ›', $html);
        $this->assertStringContainsString('mine=1', $html);

        // A second non-editing role, named with an ampersand, is listed in the site's role order
        // and escaped once.
        $tutor = create_role('Tutor & Mentor', 'tutor', '', 'teacher');
        assign_capability('local/zoomattendance:betrackedteacher', CAP_ALLOW, $tutor, \context_system::instance());
        $this->assertSame(['Teacher', 'Tutor & Mentor'], content::teacher_roles(false));
        $this->setUser($this->users['manager']);
        $html = $PAGE->get_renderer('block_zoomattendance')->overview(content::build());
        $this->assertStringContainsString('Roles: Teacher and Tutor &amp; Mentor. Last 30 days', $html);
        $this->assertStringContainsString('Every Teacher, Tutor &amp; Mentor and Coordinator ›', $html);
        $sections = \block_zoomattendance\output\mobile::sections(content::build());
        $hint = array_column($sections, 'hint', 'title')['Teacher attendance'];
        $this->assertStringStartsWith('Roles: Teacher and Tutor & Mentor. Last 30 days', $hint);
    }

    public function test_nothing_to_show_and_cache(): void {
        $this->setUser($this->users['outsider']);
        $this->assertTrue(content::is_empty(content::build()));

        // The content is cached per user until it is an hour old or Zoom attendance's data changes.
        $this->setUser($this->users['full']);
        $first = content::get();
        set_config('latepct', 10, 'local_zoomattendance');
        $this->assertSame($first, content::get());
        \local_zoomattendance\local\data_version::bump();
        $this->assertSame(10.0, content::get()['thresholds']['partial']);

        set_config('latepct', 20, 'local_zoomattendance');
        $cache = \cache::make('block_zoomattendance', 'content');
        $entry = $cache->get((int) $this->users['full']->id);
        $cache->set((int) $this->users['full']->id, ['time' => time() - content::CACHE_SECS - 1] + $entry);
        $this->assertSame(20.0, content::get()['thresholds']['partial']);
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

    public function test_app_view_lists_the_same_sections(): void {
        $this->setUser($this->users['coordinator']);
        $view = \block_zoomattendance\output\mobile::mobile_block_view([]);
        $this->assertSame('main', $view['templates'][0]['id']);
        $sections = json_decode($view['otherdata']['sections']);
        $titles = array_column($sections, 'title');
        $this->assertSame(['My students', 'Recent classes', 'My teaching', 'Teacher attendance'], $titles);
        $this->assertSame('1 of 2 low', $sections[0]->rows[0]->value);
        // The class, with its course and headcount, coloured by the student thresholds.
        $this->assertStringStartsWith('Spoken English · ', $sections[1]->rows[0]->sub);
        $this->assertStringEndsWith(' · 1 of 2 present', $sections[1]->rows[0]->sub);
        $this->assertSame('50.0%', $sections[1]->rows[0]->value);
        $this->assertSame('warning', $sections[1]->rows[0]->color);
        $this->assertSame('100.0%', $sections[2]->rows[0]->value);
        $this->assertSame('success', $sections[2]->rows[0]->color);
        // Text reaches the app as data, never inside the template.
        $this->assertStringNotContainsString('Spoken English', $view['templates'][0]['html']);

        $this->setUser($this->users['outsider']);
        $this->assertSame('[]', \block_zoomattendance\output\mobile::mobile_block_view([])['otherdata']['sections']);
    }
}
