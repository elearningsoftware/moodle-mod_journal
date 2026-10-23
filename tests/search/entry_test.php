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
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

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
                'userid' => $generator->create_user()->id,
                'modified' => $time,
                'text' => 'text',
            ],
        );

        $je2 = $DB->insert_record(
            'journal_entries',
            [
                'journal' => $journalid,
                'userid' => $generator->create_user()->id,
                'modified' => $time + 1000,
                'text' => 'text',
            ],
        );

        $je3 = $DB->insert_record(
            'journal_entries',
            [
                'journal' => $journalid,
                'userid' => $generator->create_user()->id,
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

    /**
     * In separate groups mode, graders only have access to the entries of users in their groups.
     *
     * @covers ::check_access
     */
    public function test_check_access_separate_groups(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $journal = $generator->create_module('journal', ['course' => $course->id]);
        $group1 = $generator->create_group(['courseid' => $course->id]);
        $group2 = $generator->create_group(['courseid' => $course->id]);
        $teacher = $generator->create_and_enrol($course, 'teacher');
        $student1 = $generator->create_and_enrol($course, 'student');
        $student2 = $generator->create_and_enrol($course, 'student');
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $student1->id]);
        $generator->create_group_member(['groupid' => $group2->id, 'userid' => $student2->id]);

        $journalgenerator = $generator->get_plugin_generator('mod_journal');
        $entry1 = $journalgenerator->create_entry((object) ['journal' => $journal->id, 'userid' => $student1->id]);
        $entry2 = $journalgenerator->create_entry((object) ['journal' => $journal->id, 'userid' => $student2->id]);

        $area = new entry();
        $this->setUser($teacher);
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $area->check_access($entry1));
        $this->assertEquals(\core_search\manager::ACCESS_DENIED, $area->check_access($entry2));
        $this->setUser($student2);
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $area->check_access($entry2));
        $this->assertEquals(\core_search\manager::ACCESS_DENIED, $area->check_access($entry1));
    }
}
