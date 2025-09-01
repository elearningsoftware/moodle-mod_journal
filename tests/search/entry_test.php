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

namespace mod_journal\search;

use dml_exception;

/**
 * Unit tests for the class \mod_journal\search\entry
 *
 * @package   mod_journal
 * @copyright 2025 eDaktik GmbH {@link https://www.edaktik.at/}
 * @author    Christian Abila <christian.abila@edaktik.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\search\entry
 */
final class entry_test extends \advanced_testcase {
    /**
     * Journal entries after the given timestamp are returned.
     *
     * @return void
     * @covers ::get_recordset_by_timestamp
     * @throws dml_exception
     */
    public function test_get_recordset_by_timestamp(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $time = time();
        // Create journal entries.
        $journalid = $DB->insert_record(
            'journal',
            [
                'name' => 'Journal',
                'intro' => 'Intro',
            ],
        );

        $je1 = $DB->insert_record(
            'journal_entries',
            [
                'journal' => $journalid,
                'userid' => $USER->id,
                'modified' => $time,
                'text' => 'text',
            ],
        );

        $je2 = $DB->insert_record(
            'journal_entries',
            [
                'journal' => $journalid,
                'userid' => $USER->id,
                'modified' => $time + 1000,
                'text' => 'text',
            ],
        );

        $je3 = $DB->insert_record(
            'journal_entries',
            [
                'journal' => $journalid,
                'userid' => $USER->id,
                'modified' => $time - 1000,
                'text' => 'text',
            ],
        );

        $entry = new entry();

        $entries = $entry->get_recordset_by_timestamp($time);

        $this->assertCount(2, $entries);
        $ids = [];

        foreach ($entries as $entry) {
            $this->assertTrue(in_array($entry->id, [$je1, $je2]));
            $ids[] = $entry->id;
        }

        $this->assertFalse(in_array($je3, $ids));
    }
}
