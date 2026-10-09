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

namespace mod_journal\external;

use advanced_testcase;

/**
 * Unit tests for the class \mod_journal\external\save_feedback.
 *
 * @runTestsInSeparateProcesses
 *
 * @package   mod_journal
 * @copyright 2026 ISB Bayern
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\external\save_feedback
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class save_feedback_test extends advanced_testcase {
    /** @var \stdClass Journal instance. */
    private \stdClass $journal;

    /** @var \stdClass Grader in group A only. */
    private \stdClass $teacher;

    /** @var \stdClass Student in group A. */
    private \stdClass $studenta;

    /** @var \stdClass Student in group B. */
    private \stdClass $studentb;

    /**
     * Create a separate-groups journal with a non-editing teacher in group A.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $this->journal = $generator->create_module('journal', ['course' => $course->id, 'grade' => 100]);
        $groupa = $generator->create_group(['courseid' => $course->id]);
        $groupb = $generator->create_group(['courseid' => $course->id]);

        $this->teacher = $generator->create_and_enrol($course, 'teacher');
        $this->studenta = $generator->create_and_enrol($course, 'student');
        $this->studentb = $generator->create_and_enrol($course, 'student');
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $this->teacher->id]);
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $this->studenta->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $this->studentb->id]);
    }

    /**
     * Insert an ungraded entry for the given student.
     *
     * @param \stdClass $student
     * @return int Entry id
     */
    private function create_entry(\stdClass $student): int {
        global $DB;
        return $DB->insert_record('journal_entries', (object) [
            'journal' => $this->journal->id,
            'userid' => $student->id,
            'text' => 'entry',
            'format' => FORMAT_HTML,
            'modified' => time(),
            'rating' => null,
            'entrycomment' => null,
            'teacher' => 0,
            'timemarked' => 0,
            'mailed' => 0,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * A grader must not write feedback onto an entry of a student in another separate group.
     *
     * @covers ::execute
     */
    public function test_execute_rejects_entry_outside_graders_groups(): void {
        global $DB;
        $entryid = $this->create_entry($this->studentb);
        $this->setUser($this->teacher);

        try {
            save_feedback::execute($this->journal->cmid, $entryid, $this->studentb->id, 80, 'Feedback');
            $this->fail('Expected moodle_exception was not thrown');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode, $e->getMessage());
        }
        $entry = $DB->get_record('journal_entries', ['id' => $entryid], '*', MUST_EXIST);
        $this->assertNull($entry->rating);
        $this->assertNull($entry->entrycomment);
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * A grader can still write feedback onto an entry of a student in their own group.
     *
     * @covers ::execute
     */
    public function test_execute_accepts_entry_in_graders_group(): void {
        global $DB;
        $entryid = $this->create_entry($this->studenta);
        $this->setUser($this->teacher);

        $result = save_feedback::execute($this->journal->cmid, $entryid, $this->studenta->id, 80, 'Feedback');

        $this->assertSame(1, $result['changed']);
        $entry = $DB->get_record('journal_entries', ['id' => $entryid], '*', MUST_EXIST);
        $this->assertEquals(80, $entry->rating);
        $this->assertSame('Feedback', $entry->entrycomment);
        $this->assertEquals($this->teacher->id, $entry->teacher);
    }
}
