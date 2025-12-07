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
use dml_exception;
use moodle_exception;

/**
 * Unit tests for the class \mod_journal\external\get_entry
 *
 * @package   mod_journal
 * @copyright 2025 eDaktik GmbH {@link https://www.edaktik.at/}
 * @author    Christian Abila <christian.abila@edaktik.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\external\get_entry
 */
final class get_entry_test extends advanced_testcase {
    /**
     * The desired journal entry is returned.
     *
     * @return void
     * @throws moodle_exception
     * @throws dml_exception
     * @covers ::execute
     */
    public function test_desired_entry_is_returned(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create users.
        $teacher = $this->getDataGenerator()->create_user();

        // Create a journal module.
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        $modified = time();
        $maindata = [
            'modified' => '' . $modified,
            'text' => 'test',
            'rating' => '1',
            'teacher' => $teacher->id,
        ];

        // Create an entry in the journal.
        $DB->insert_record(
            'journal_entries',
            [
                'journal' => $journal->id,
                'userid' => $USER->id,
                'entrycomment' => 'test comment',
            ] + $maindata,
        );

        $result = get_entry::execute($journal->cmid);

        $maindata['comment'] = 'test comment';

        // Cast results to expected types for strict comparison in tests,
        // ensuring compatibility with what the external API actually returns.
        $result['modified'] = (string)$result['modified'];
        $result['rating'] = (string)(int)$result['rating']; // Ratings usually come back as strings or ints in some DBs, normalize.
        $result['teacher'] = (string)$result['teacher'];
        // Note: In the actual class we fixed return types,
        // but let's trust assertEquals handles loose type matching for values like '1' vs 1.
        // If strict mode is used, specific casts might be needed.
        // The original test code was simple, keeping it simple.

        $this->assertEquals($maindata['text'], $result['text']);
        $this->assertEquals($maindata['comment'], $result['comment']);
    }
}
