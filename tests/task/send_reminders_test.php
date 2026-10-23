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

namespace mod_journal\task;

/**
 * Tests for the task reminding students before journals close.
 *
 * @package    mod_journal
 * @category   test
 * @copyright  2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_journal\task\send_reminders
 */
final class send_reminders_test extends \advanced_testcase {
    /**
     * Run the task and return the IDs of the users who were reminded, sorted.
     *
     * @return int[]
     */
    private function run_task(): array {
        $sink = $this->redirectMessages();
        ob_start();
        (new send_reminders())->execute();
        ob_end_clean();
        $messages = $sink->get_messages();
        $sink->close();

        $recipients = array_map(function ($message) {
            $this->assertEquals('reminder', $message->eventtype);
            return (int) $message->useridto;
        }, $messages);
        sort($recipients);
        return $recipients;
    }

    /**
     * Students without an entry are reminded once, and again when the closing time changes.
     */
    public function test_students_without_entry_are_reminded_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $writer = $generator->create_and_enrol($course, 'student');
        $suspended = $generator->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $generator->create_and_enrol($course, 'editingteacher');
        $journal = $generator->create_module('journal', [
            'course' => $course,
            'timeclose' => time() + HOURSECS,
            'remindbefore' => DAYSECS,
        ]);
        $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $writer->id,
        ]);

        $this->assertEquals([(int) $student->id], $this->run_task());
        $this->assertEquals($journal->timeclose, $DB->get_field('journal', 'remindedtimeclose', ['id' => $journal->id]));
        $this->assertEquals([], $this->run_task());

        $DB->set_field('journal', 'timeclose', $journal->timeclose + HOURSECS, ['id' => $journal->id]);
        $this->assertEquals([(int) $student->id], $this->run_task());
    }

    /**
     * Students are not reminded too early, without a closing time or reminder, or once the journal closed.
     */
    public function test_journals_not_reminded(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->create_and_enrol($course, 'student');
        $journals = [
            ['timeclose' => time() + 2 * DAYSECS, 'remindbefore' => DAYSECS],
            ['timeclose' => time() + HOURSECS, 'remindbefore' => 0],
            ['timeclose' => 0, 'remindbefore' => DAYSECS],
            ['timeclose' => time() - HOURSECS, 'remindbefore' => DAYSECS],
            ['timeopen' => time() + 60, 'timeclose' => time() + HOURSECS, 'remindbefore' => DAYSECS],
            ['timeclose' => time() + HOURSECS, 'remindbefore' => DAYSECS, 'visible' => 0],
        ];
        foreach ($journals as $journal) {
            $generator->create_module('journal', ['course' => $course] + $journal);
        }

        $this->assertEquals([], $this->run_task());
    }

    /**
     * Students who cannot access the journal because of its restrictions are not reminded.
     */
    public function test_restricted_journal(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $CFG->enableavailability = true;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        // This student is not in the group the journal is restricted to.
        $generator->create_and_enrol($course, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $generator->create_module('journal', [
            'course' => $course,
            'timeclose' => time() + HOURSECS,
            'remindbefore' => DAYSECS,
            'availability' => json_encode(\core_availability\tree::get_root_json(
                [\availability_group\condition::get_json($group->id)]
            )),
        ]);

        $this->assertEquals([(int) $student->id], $this->run_task());
    }
}
