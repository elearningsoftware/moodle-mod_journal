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
use coding_exception;
use dml_exception;
use invalid_parameter_exception;
use required_capability_exception;

/**
 * Unit tests for the class \mod_journal\external\set_text
 *
 * @runTestsInSeparateProcesses
 *
 * @package   mod_journal
 * @copyright 2025 eDaktik GmbH {@link https://www.edaktik.at/}
 * @author    Christian Abila <christian.abila@edaktik.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\external\set_text
 */
final class set_text_test extends advanced_testcase {
    /**
     * The given text is set in the selected journal.
     *
     * @return void
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws dml_exception
     * @covers ::execute
     */
    public function test_text_is_set_in_journal(): void {
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

        $result = set_text::execute($journal->id, 'newtext', FORMAT_PLAIN);

        $this->assertEquals('newtext', $result['text']);

        $result = $DB->get_record('journal_entries', ['journal' => $journal->id, 'userid' => $USER->id]);

        $this->assertEquals($journal->id, $result->journal);
    }

    /**
     * The journal can be given by its course module ID instead, which takes precedence over the instance ID.
     *
     * @return void
     * @covers ::execute
     */
    public function test_text_is_set_in_journal_by_cmid(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $journal1 = $this->getDataGenerator()->create_module('journal', ['course' => $course]);
        $journal2 = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        $result = set_text::execute(0, 'bycmid', FORMAT_PLAIN, 0, $journal2->cmid);
        $this->assertEquals('bycmid', $result['text']);
        $this->assertEquals('bycmid', $DB->get_field('journal_entries', 'text', [
            'journal' => $journal2->id,
            'userid' => $USER->id,
        ]));

        set_text::execute($journal1->id, 'precedence', FORMAT_PLAIN, 0, $journal2->cmid);
        $this->assertFalse($DB->record_exists('journal_entries', ['journal' => $journal1->id]));
        $this->assertEquals('precedence', $DB->get_field('journal_entries', 'text', ['journal' => $journal2->id]));
    }

    /**
     * Text with an unknown format is rejected.
     *
     * @return void
     * @covers ::execute
     */
    public function test_text_with_invalid_format_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        $this->expectException(invalid_parameter_exception::class);
        try {
            set_text::execute($journal->id, 'newtext', 99);
        } finally {
            $this->assertFalse($DB->record_exists('journal_entries', ['journal' => $journal->id]));
        }
    }

    /**
     * No text can be set once the editing period of the journal has ended.
     *
     * @return void
     * @covers ::execute
     */
    public function test_text_is_not_set_after_editing_period(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'weeks', 'startdate' => time() - 4 * WEEKSECS]);
        $student = $generator->create_and_enrol($course, 'student');
        $journal = $generator->create_module('journal', ['course' => $course, 'section' => 1, 'days' => 7]);
        $this->setUser($student);

        try {
            set_text::execute($journal->id, 'newtext', FORMAT_PLAIN);
            $this->fail('An exception should have been thrown.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('journalclosed', $e->errorcode);
        }

        $this->assertFalse($DB->record_exists('journal_entries', ['journal' => $journal->id]));
    }
}
