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
use coding_exception;
use dml_exception;

/**
 * Tests for the journal manager, specifically has_answered().
 *
 * Guards bug B4: the previous implementation called
 * \journal_get_completion_state($course, $cm, $userid, true). The
 * underlying helper only checks completion when $type === COMPLETION_AND,
 * so for any other value it just returned $type unchanged. That made
 * has_answered() return true for every student regardless of whether
 * they had actually written an entry, painting the "completed" tick on
 * the course-format overview in error.
 *
 * @package   mod_journal
 * @copyright 2025 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\manager
 */
final class manager_test extends advanced_testcase {
    /**
     * A student with no entry has not answered.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @covers ::has_answered
     */
    public function test_has_answered_false_without_entry(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        $this->setUser($student);

        $manager = manager::create_from_instance((object)['id' => $journal->id]);
        $this->assertFalse($manager->has_answered());
    }

    /**
     * A student with an entry has answered.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @covers ::has_answered
     */
    public function test_has_answered_true_with_entry(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        $this->setUser($student);

        $this->getDataGenerator()->get_plugin_generator('mod_journal')->create_entry((object)[
            'journal' => $journal->id,
            'userid' => $student->id,
        ]);

        $manager = manager::create_from_instance((object)['id' => $journal->id]);
        $this->assertTrue($manager->has_answered());
    }

    /**
     * A second student's entry does not make the first student appear as having answered.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @covers ::has_answered
     */
    public function test_has_answered_is_user_specific(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        // Only student2 has an entry.
        $this->getDataGenerator()->get_plugin_generator('mod_journal')->create_entry((object)[
            'journal' => $journal->id,
            'userid' => $student2->id,
        ]);

        $manager = manager::create_from_instance((object)['id' => $journal->id]);

        $this->setUser($student1);
        $this->assertFalse($manager->has_answered(), 'student1 must not be reported as having answered');

        $this->setUser($student2);
        $this->assertTrue($manager->has_answered(), 'student2 must be reported as having answered');
    }

    /**
     * Entries that were never graded or were edited since they were graded need grading.
     *
     * @return void
     * @covers ::count_all_users_needing_grading
     * @covers ::journal_entry_needs_grading
     */
    public function test_count_all_users_needing_grading(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $ungraded = $generator->create_and_enrol($course, 'student');
        $graded = $generator->create_and_enrol($course, 'student');
        $edited = $generator->create_and_enrol($course, 'student');
        $generator->create_and_enrol($course, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $ungraded->id]);
        $journal = $generator->create_module('journal', ['course' => $course]);

        $journalgenerator = $generator->get_plugin_generator('mod_journal');
        $journalgenerator->create_entry((object) ['journal' => $journal->id, 'userid' => $ungraded->id, 'modified' => 100]);
        $journalgenerator->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $graded->id,
            'modified' => 100,
            'timemarked' => 200,
        ]);
        $journalgenerator->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $edited->id,
            'modified' => 300,
            'timemarked' => 200,
        ]);

        $entries = $DB->get_records('journal_entries', ['journal' => $journal->id], '', 'userid, modified, timemarked');
        $this->assertTrue(journal_entry_needs_grading($entries[$ungraded->id]));
        $this->assertFalse(journal_entry_needs_grading($entries[$graded->id]));
        $this->assertTrue(journal_entry_needs_grading($entries[$edited->id]));
        $this->assertFalse(journal_entry_needs_grading(null));

        $manager = manager::create_from_instance((object) ['id' => $journal->id]);
        $this->setUser($teacher);
        $this->assertEquals(2, $manager->count_all_users_needing_grading());
        $this->assertEquals(1, $manager->count_all_users_needing_grading([$group->id]));

        // Only graders can see what needs grading.
        $this->setUser($ungraded);
        $this->assertEquals(0, $manager->count_all_users_needing_grading());
    }

    /**
     * Saving the entry of a user creates it, then updates it, with events about the entry.
     *
     * @return void
     * @covers ::save_entry
     */
    public function test_save_entry(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $journal = $generator->create_module('journal', [
            'course' => $course,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completion_create_entry' => 1,
        ]);
        $cm = get_coursemodule_from_instance('journal', $journal->id);
        $this->setUser($student);
        $manager = manager::create_from_coursemodule($cm);

        $sink = $this->redirectEvents();
        $entry = $manager->save_entry('First text', FORMAT_HTML);
        $manager->save_entry('Second text', FORMAT_PLAIN);
        $events = array_values(array_filter($sink->get_events(), function ($event) {
            return $event->component === 'mod_journal';
        }));
        $sink->close();

        $saved = $DB->get_record('journal_entries', ['journal' => $journal->id, 'userid' => $student->id], '*', MUST_EXIST);
        $this->assertEquals($entry->id, $saved->id);
        $this->assertEquals('Second text', $saved->text);
        $this->assertEquals(FORMAT_PLAIN, $saved->format);

        $this->assertCount(2, $events);
        $this->assertInstanceOf(event\entry_created::class, $events[0]);
        $this->assertInstanceOf(event\entry_updated::class, $events[1]);
        foreach ($events as $event) {
            $this->assertEquals($entry->id, $event->objectid);
            $this->assertEquals($student->id, $event->relateduserid);
        }

        $completion = new \completion_info($course);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
    }

    /**
     * Saving feedback updates the entry and the gradebook, once per change.
     *
     * @return void
     * @covers ::save_feedback
     */
    public function test_save_feedback(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $journal = $generator->create_module('journal', ['course' => $course, 'grade' => 10]);
        $entryid = $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $student->id,
        ]);
        $entry = $DB->get_record('journal_entries', ['id' => $entryid]);
        $this->setUser($teacher);
        $manager = manager::create_from_coursemodule(get_coursemodule_from_instance('journal', $journal->id));

        $sink = $this->redirectEvents();
        $this->assertTrue($manager->save_feedback($entry, 7, '<p>Well done</p>'));
        $this->assertFalse($manager->save_feedback($entry, 7, '<p>Well done</p>'));
        $events = array_values(array_filter($sink->get_events(), function ($event) {
            return $event instanceof event\feedback_updated;
        }));
        $sink->close();

        $saved = $DB->get_record('journal_entries', ['id' => $entryid]);
        $this->assertEquals(7, $saved->rating);
        $this->assertEquals('<p>Well done</p>', $saved->entrycomment);
        $this->assertEquals($teacher->id, $saved->teacher);
        $this->assertEquals(7, $entry->rating);

        $grades = grade_get_grades($course->id, 'mod', 'journal', $journal->id, $student->id);
        $this->assertEquals(7, $grades->items[0]->grades[$student->id]->grade);

        $this->assertCount(1, $events);
        $this->assertEquals($entryid, $events[0]->objectid);
        $this->assertEquals($student->id, $events[0]->relateduserid);
    }

    /**
     * A grade outside the grade menu is rejected, unless it is the current grade sent back unchanged.
     *
     * @return void
     * @covers ::save_feedback
     */
    public function test_save_feedback_invalid_grade(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $journal = $generator->create_module('journal', ['course' => $course, 'grade' => 10]);
        $entryid = $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $student->id,
            'rating' => 50,
        ]);
        $entry = $DB->get_record('journal_entries', ['id' => $entryid]);
        $this->setUser($teacher);
        $manager = manager::create_from_coursemodule(get_coursemodule_from_instance('journal', $journal->id));

        $this->assertTrue($manager->save_feedback($entry, 50, '<p>Feedback</p>'));

        $this->expectException(\invalid_parameter_exception::class);
        $manager->save_feedback($entry, 11, '<p>Feedback</p>');
    }
}
