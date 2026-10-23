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

namespace mod_journal\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;

/**
 * Tests for the privacy provider of mod_journal.
 *
 * @package    mod_journal
 * @category   test
 * @copyright  2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_journal\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \context_module Context of the journal. */
    private $context;

    /** @var \stdClass First student. */
    private $student1;

    /** @var \stdClass Second student. */
    private $student2;

    /** @var int[] Entry IDs by user ID. */
    private $entryids = [];

    /**
     * Creates a journal with an entry for two students, each entry with an entry and a feedback file.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $journalgenerator = $generator->get_plugin_generator('mod_journal');
        $course = $generator->create_course();
        $journal = $journalgenerator->create_instance(['course' => $course->id]);
        $this->context = \context_module::instance($journal->cmid);
        $this->student1 = $generator->create_and_enrol($course, 'student');
        $this->student2 = $generator->create_and_enrol($course, 'student');

        foreach ([$this->student1, $this->student2] as $student) {
            $entryid = $journalgenerator->create_entry((object)['journal' => $journal->id, 'userid' => $student->id]);
            $this->entryids[$student->id] = $entryid;
            foreach (['entry', 'feedback'] as $filearea) {
                get_file_storage()->create_file_from_string([
                    'contextid' => $this->context->id,
                    'component' => 'mod_journal',
                    'filearea' => $filearea,
                    'itemid' => $entryid,
                    'filepath' => '/',
                    'filename' => 'file.txt',
                ], 'File content');
            }
        }
    }

    /**
     * Deleting the data of all users deletes all entries and their files.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertEquals(0, $DB->count_records('journal_entries'));
        $this->assertEmpty($this->get_entry_file_itemids());
    }

    /**
     * Deleting the data of a user deletes only the entry and the files of that user.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        provider::delete_data_for_user(new approved_contextlist($this->student1, 'mod_journal', [$this->context->id]));

        $this->assertEquals([$this->student2->id], $DB->get_fieldset_select('journal_entries', 'userid', '1 = 1'));
        $this->assertEquals([$this->entryids[$this->student2->id]], $this->get_entry_file_itemids());
    }

    /**
     * Deleting the data of a list of users deletes only the entries and the files of those users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_journal', [$this->student2->id]));

        $this->assertEquals([$this->student1->id], $DB->get_fieldset_select('journal_entries', 'userid', '1 = 1'));
        $this->assertEquals([$this->entryids[$this->student1->id]], $this->get_entry_file_itemids());
    }

    /**
     * Exporting the data of a grader exports all the entries they graded in each journal, with their files.
     */
    public function test_export_user_data_of_grader(): void {
        $generator = $this->getDataGenerator();
        $journalgenerator = $generator->get_plugin_generator('mod_journal');
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $journals = [
            $journalgenerator->create_instance(['course' => $course->id]),
            $journalgenerator->create_instance(['course' => $course->id]),
        ];

        // The entries of the two journals are created in turns.
        $entryids = [];
        foreach ([0, 1, 0] as $index) {
            $journal = $journals[$index];
            $student = $generator->create_and_enrol($course, 'student');
            $entryid = $journalgenerator->create_entry((object) [
                'journal' => $journal->id,
                'userid' => $student->id,
                'text' => '<img src="@@PLUGINFILE@@/entry.png" alt="Entry">',
                'format' => FORMAT_HTML,
                'entrycomment' => '<img src="@@PLUGINFILE@@/feedback.png" alt="Feedback">',
                'teacher' => $teacher->id,
                'timemarked' => time(),
            ]);
            $entryids[$index][] = $entryid;
            foreach (['entry', 'feedback'] as $filearea) {
                get_file_storage()->create_file_from_string([
                    'contextid' => \context_module::instance($journal->cmid)->id,
                    'component' => 'mod_journal',
                    'filearea' => $filearea,
                    'itemid' => $entryid,
                    'filepath' => '/',
                    'filename' => $filearea . '.png',
                ], $filearea);
            }
        }

        $contextids = array_map(function ($journal) {
            return \context_module::instance($journal->cmid)->id;
        }, $journals);
        provider::export_user_data(new approved_contextlist($teacher, 'mod_journal', $contextids));

        foreach ($journals as $index => $journal) {
            $writer = \core_privacy\local\request\writer::with_context(\context_module::instance($journal->cmid));
            $entries = $writer->get_related_data([], 'entries');
            $this->assertCount(count($entryids[$index]), $entries);
            foreach ($entryids[$index] as $entryid) {
                foreach (['entry', 'feedback'] as $filearea) {
                    $files = $writer->get_files(['entries', $entryid, $filearea]);
                    $this->assertArrayHasKey($filearea . '.png', $files);
                }
            }
            $this->assertStringNotContainsString('@@PLUGINFILE@@', $entries[0]['text']);
            $this->assertStringNotContainsString('@@PLUGINFILE@@', $entries[0]['entrycomment']);
        }
    }

    /**
     * Returns the distinct item IDs of the entry and feedback files left in the journal.
     *
     * @return int[]
     */
    private function get_entry_file_itemids(): array {
        $itemids = [];
        foreach (['entry', 'feedback'] as $filearea) {
            $files = get_file_storage()->get_area_files($this->context->id, 'mod_journal', $filearea, false, 'itemid', false);
            foreach ($files as $file) {
                $itemids[] = (int) $file->get_itemid();
            }
        }
        return array_values(array_unique($itemids));
    }
}
