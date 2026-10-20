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
 * Tests for the journal cron task.
 *
 * @package    mod_journal
 * @category   test
 * @copyright  2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_journal\task\cron_task
 */
final class cron_task_test extends \advanced_testcase {
    /**
     * Graded entries the task does not notify about are marked as mailed, so they are not processed again.
     */
    public function test_skipped_entries_are_marked_mailed(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $journalgenerator = $generator->get_plugin_generator('mod_journal');
        $course = $generator->create_course();
        $journal = $journalgenerator->create_instance(['course' => $course->id, 'notifystudents' => 1]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $manager = $generator->create_and_enrol($course, 'manager');
        $student = $generator->create_and_enrol($course, 'student');

        $graded = [
            'journal' => $journal->id,
            'rating' => 50,
            'teacher' => $teacher->id,
            'timemarked' => time() - DAYSECS,
        ];
        // A manager can manage entries but not add them, so is not an active participant.
        $managerentryid = $journalgenerator->create_entry((object)($graded + ['userid' => $manager->id]));
        // The teacher who graded the entry no longer exists.
        $orphanentryid = $journalgenerator->create_entry((object)(['teacher' => -1] + $graded + ['userid' => $student->id]));

        $sink = $this->redirectMessages();
        $this->expectOutputRegex('/Could not find teacher -1/');
        (new cron_task())->execute();

        $this->assertEquals(0, $sink->count());
        $this->assertEquals(1, $DB->get_field('journal_entries', 'mailed', ['id' => $managerentryid]));
        $this->assertEquals(1, $DB->get_field('journal_entries', 'mailed', ['id' => $orphanentryid]));
    }
}
