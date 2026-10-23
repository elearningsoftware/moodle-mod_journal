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

namespace mod_journal\output;

use moodle_page;

/**
 * Tests for the views of the mobile app.
 *
 * @package   mod_journal
 * @category  test
 * @copyright 2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\output\mobile
 */
final class mobile_test extends \advanced_testcase {
    /**
     * Reset the page, set up by each view, and keep the enrolment emails from rendering templates.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $PAGE = new moodle_page();
        $this->redirectMessages();
    }

    /**
     * Returns the HTML of the main view of a journal.
     *
     * @param \stdClass $journal The journal
     * @return string
     */
    private function render_course_view(\stdClass $journal): string {
        $result = mobile::mobile_course_view(['cmid' => $journal->cmid, 'courseid' => $journal->course]);
        return $result['templates'][0]['html'];
    }

    /**
     * A grade of 0 is shown to the student, even without feedback text.
     *
     * @covers ::mobile_course_view
     */
    public function test_course_view_shows_grade_zero(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $journal = $generator->create_module('journal', ['course' => $course->id, 'grade' => 10]);
        $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $student->id,
            'rating' => 0,
            'teacher' => get_admin()->id,
            'timemarked' => time(),
        ]);
        journal_update_grades($DB->get_record('journal', ['id' => $journal->id]), $student->id);

        $this->setUser($student);
        $html = $this->render_course_view($journal);

        $this->assertStringContainsString(fullname(get_admin()), $html);
        $this->assertMatchesRegularExpression('~</strong> 0\.00 / 10\.00</p>~', $html);
    }

    /**
     * In separate groups mode, a grader who is in no group and cannot access all groups sees no entries.
     *
     * @covers ::mobile_course_view
     */
    public function test_course_view_hides_entries_from_grader_without_group(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $journal = $generator->create_module('journal', ['course' => $course->id]);
        $group = $generator->create_group(['courseid' => $course->id]);
        $teacher = $generator->create_and_enrol($course, 'teacher');
        $student = $generator->create_and_enrol($course, 'student', ['firstname' => 'Hidden', 'lastname' => 'Student']);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $student->id,
        ]);

        $this->setUser($teacher);
        $this->assertStringNotContainsString('Hidden Student', $this->render_course_view($journal));

        $generator->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        $this->setUser($teacher);
        $this->assertStringContainsString('Hidden Student', $this->render_course_view($journal));
    }

    /**
     * The edit page shows the limits of the number of words to students.
     *
     * @covers ::mobile_entry_edit
     */
    public function test_entry_edit_shows_word_limit(): void {
        global $PAGE;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $limited = $generator->create_module('journal', ['course' => $course->id, 'minwords' => 50]);
        $unlimited = $generator->create_module('journal', ['course' => $course->id]);

        $this->setUser($student);
        $result = mobile::mobile_entry_edit(['cmid' => $limited->cmid, 'courseid' => $course->id]);
        $this->assertStringContainsString('Number of words: At least 50', $result['templates'][0]['html']);
        $PAGE = new moodle_page();
        $result = mobile::mobile_entry_edit(['cmid' => $unlimited->cmid, 'courseid' => $course->id]);
        $this->assertStringNotContainsString('Number of words', $result['templates'][0]['html']);
    }
}
