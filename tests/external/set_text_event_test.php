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
use mod_journal\event\entry_created;
use mod_journal\event\entry_updated;
use required_capability_exception;

/**
 * Extra unit tests for \mod_journal\external\set_text covering the
 * create-vs-update event selection.
 *
 * Guards bug B2: a previous implementation always fired entry_updated,
 * because the $entry local got reassigned to a $newentry whose
 * "modified" field was already set to time() — so the predicate
 * $entry->modified > 0 was always true. The fix tracks a dedicated
 * $isnew flag and chooses the right event class.
 *
 * @runTestsInSeparateProcesses
 *
 * @package   mod_journal
 * @copyright 2025 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\external\set_text
 */
final class set_text_event_test extends advanced_testcase {
    /**
     * First save fires entry_created, never entry_updated.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @covers ::execute
     */
    public function test_first_save_fires_entry_created(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        $sink = $this->redirectEvents();

        $result = set_text::execute($journal->id, 'first text', FORMAT_PLAIN);

        $events = $sink->get_events();
        $sink->close();

        $this->assertEquals('first text', $result['text']);
        $this->assertCount(1, $events, 'Exactly one event should fire for the first save.');

        $event = reset($events);
        $this->assertInstanceOf(entry_created::class, $event);
        $this->assertNotInstanceOf(entry_updated::class, $event);

        global $DB, $USER;
        $entry = $DB->get_record('journal_entries', ['journal' => $journal->id, 'userid' => $USER->id], '*', MUST_EXIST);
        $this->assertEquals($entry->id, $event->objectid);
        $this->assertEquals($USER->id, $event->relateduserid);
    }

    /**
     * Second save fires entry_updated, not entry_created.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @covers ::execute
     */
    public function test_second_save_fires_entry_updated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        // Prime: first save creates the row + entry_created event.
        set_text::execute($journal->id, 'first text', FORMAT_PLAIN);

        $sink = $this->redirectEvents();

        $result = set_text::execute($journal->id, 'second text', FORMAT_PLAIN);

        $events = $sink->get_events();
        $sink->close();

        $this->assertEquals('second text', $result['text']);
        $this->assertCount(1, $events, 'Exactly one event should fire for the update.');

        $event = reset($events);
        $this->assertInstanceOf(entry_updated::class, $event);
        $this->assertNotInstanceOf(entry_created::class, $event);

        global $DB, $USER;
        $entry = $DB->get_record('journal_entries', ['journal' => $journal->id, 'userid' => $USER->id], '*', MUST_EXIST);
        $this->assertEquals($entry->id, $event->objectid);
        $this->assertEquals($USER->id, $event->relateduserid);
    }
}
