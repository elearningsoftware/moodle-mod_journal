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

/**
 * Tests for Journal
 *
 * @package    mod_journal
 * @category   test
 * @copyright  2025 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * Test reset function.
     *
     * @covers ::journal_reset_userdata
     */
    public function test_reset(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $journalgenerator = $generator->get_plugin_generator('mod_journal');

        // Create a course.
        $course = $generator->create_course();

        // Create a journal instance.
        $journal = $journalgenerator->create_instance(['course' => $course->id]);

        $entryrecord = (object)['journal' => $journal->id];
        $entry = $journalgenerator->create_entry($entryrecord);

        $status = journal_reset_userdata((object)['reset_journal' => true, 'courseid' => $course->id]);
        $this->assertCount(1, $status);
        $this->assertFalse($status[0]['error']);
    }
}
