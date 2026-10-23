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

namespace mod_journal;

use advanced_testcase;

/**
 * Tests for the notifications sent to teachers when entries are saved.
 *
 * @package   mod_journal
 * @copyright 2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\event_observer
 */
final class event_observer_test extends advanced_testcase {
    /**
     * In separate groups mode, only teachers sharing a group with the student or accessing all groups are notified.
     *
     * @return void
     * @covers ::entry_modified
     */
    public function test_entry_modified_notifies_teachers_of_the_group(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $groupteacher = $generator->create_and_enrol($course, 'teacher');
        $otherteacher = $generator->create_and_enrol($course, 'teacher');
        $editingteacher = $generator->create_and_enrol($course, 'editingteacher');
        $group = $generator->create_group(['courseid' => $course->id]);
        $othergroup = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $groupteacher->id]);
        $generator->create_group_member(['groupid' => $othergroup->id, 'userid' => $otherteacher->id]);
        $journal = $generator->create_module('journal', [
            'course' => $course,
            'groupmode' => SEPARATEGROUPS,
            'notifyteachers' => 1,
        ]);

        $this->setUser($student);
        $sink = $this->redirectMessages();
        manager::create_from_coursemodule(get_coursemodule_from_instance('journal', $journal->id))
            ->save_entry('My entry', FORMAT_HTML);
        $messages = $sink->get_messages();
        $sink->close();

        $recipients = array_map(function ($message) {
            return $message->useridto;
        }, $messages);
        sort($recipients);
        $expected = [$groupteacher->id, $editingteacher->id];
        sort($expected);
        $this->assertEquals($expected, $recipients);
    }

    /**
     * No teacher is notified when the journal does not notify teachers.
     *
     * @return void
     * @covers ::entry_modified
     */
    public function test_entry_modified_respects_setting(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $generator->create_and_enrol($course, 'editingteacher');
        $journal = $generator->create_module('journal', ['course' => $course, 'notifyteachers' => 0]);

        $this->setUser($student);
        $sink = $this->redirectMessages();
        manager::create_from_coursemodule(get_coursemodule_from_instance('journal', $journal->id))
            ->save_entry('My entry', FORMAT_HTML);
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }
}
