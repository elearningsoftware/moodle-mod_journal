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

namespace mod_journal\backup;

/**
 * Tests for backing up and restoring journals.
 *
 * @package    mod_journal
 * @category   test
 * @copyright  2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_journal_activity_structure_step
 * @covers     \restore_journal_activity_structure_step
 */
final class restore_test extends \advanced_testcase {
    /** @var int Start date of the original course. */
    private const STARTDATE = 1767225600;

    /**
     * Load the backup and restore libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        parent::setUpBeforeClass();
    }

    /**
     * Backs up a course and restores it into a new course.
     *
     * @param \stdClass $course Course to back up
     * @param int $startdate Start date of the new course
     * @param bool $userdata Whether to include user data
     * @return int ID of the new course
     */
    private function backup_and_restore(\stdClass $course, int $startdate, bool $userdata): int {
        global $CFG, $USER;

        // Keep the backup directory for the restore.
        $CFG->keeptempdirectoriesonbackup = true;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($userdata);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'RESTORED', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('course_startdate')->set_value($startdate);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Action events are not restored, but created again by an adhoc task.
        $this->expectOutputRegex('/Refreshing events for journal/');
        $this->runAdhocTasks();

        return $newcourseid;
    }

    /**
     * Creates a course with a journal, an entry with files and feedback, and calendar events.
     *
     * @return array The course, the journal and the student
     */
    private function create_journal(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['startdate' => self::STARTDATE]);
        $student = $generator->create_and_enrol($course, 'student');
        $journal = $generator->create_module('journal', [
            'course' => $course->id,
            'name' => 'Backed up journal',
            'days' => 14,
            'grade' => 50,
            'timeopen' => self::STARTDATE + DAYSECS,
            'timeclose' => self::STARTDATE + WEEKSECS,
            'notifyteachers' => 0,
            'notifystudents' => 1,
            'completion_create_entry' => 1,
        ]);

        $entryid = $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $student->id,
            'text' => 'My entry',
            'format' => FORMAT_HTML,
            'modified' => self::STARTDATE + 2 * DAYSECS,
            'rating' => 40,
            'entrycomment' => 'Good work',
            'teacher' => get_admin()->id,
            'timemarked' => self::STARTDATE + 3 * DAYSECS,
            'mailed' => 1,
        ]);
        $context = \context_module::instance($journal->cmid);
        foreach (['entry', 'feedback'] as $area) {
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_journal',
                'filearea' => $area,
                'itemid' => $entryid,
                'filepath' => '/',
                'filename' => $area . '.txt',
            ], $area);
        }

        return [$course, $DB->get_record('journal', ['id' => $journal->id]), $student];
    }

    /**
     * Restoring a journal with user data restores its settings, entries, files and dates, moved with the course.
     */
    public function test_restore_with_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $journal, $student] = $this->create_journal();
        $offset = 2 * WEEKSECS;
        $newcourseid = $this->backup_and_restore($course, self::STARTDATE + $offset, true);

        $restored = $DB->get_record('journal', ['course' => $newcourseid], '*', MUST_EXIST);
        foreach (['name', 'intro', 'days', 'grade', 'notifyteachers', 'notifystudents', 'completion_create_entry'] as $field) {
            $this->assertEquals($journal->$field, $restored->$field, $field);
        }
        $this->assertEquals($journal->timeopen + $offset, $restored->timeopen);
        $this->assertEquals($journal->timeclose + $offset, $restored->timeclose);

        $events = $DB->get_records('event', ['modulename' => 'journal', 'instance' => $restored->id], '', 'eventtype, timestart');
        $this->assertEquals($restored->timeopen, $events[JOURNAL_EVENT_TYPE_OPEN]->timestart);
        $this->assertEquals($restored->timeclose, $events[JOURNAL_EVENT_TYPE_CLOSE]->timestart);

        $entry = $DB->get_record('journal_entries', ['journal' => $restored->id], '*', MUST_EXIST);
        $this->assertEquals($student->id, $entry->userid);
        $this->assertEquals('My entry', $entry->text);
        $this->assertEquals(40, $entry->rating);
        $this->assertEquals('Good work', $entry->entrycomment);

        $cm = get_coursemodule_from_instance('journal', $restored->id);
        $context = \context_module::instance($cm->id);
        foreach (['entry', 'feedback'] as $area) {
            $files = get_file_storage()->get_area_files($context->id, 'mod_journal', $area, $entry->id, '', false);
            $this->assertCount(1, $files, $area);
            $this->assertEquals($area, reset($files)->get_content());
        }
    }

    /**
     * Restoring a journal without user data restores no entries.
     */
    public function test_restore_without_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $journal] = $this->create_journal();
        $newcourseid = $this->backup_and_restore($course, self::STARTDATE, false);

        $restored = $DB->get_record('journal', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals($journal->timeopen, $restored->timeopen);
        $this->assertFalse($DB->record_exists('journal_entries', ['journal' => $restored->id]));
    }

    /**
     * Restoring a journal graded with a course scale uses the restored scale, and does not notify students again.
     */
    public function test_restore_with_course_scale(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['startdate' => self::STARTDATE]);
        $student = $generator->create_and_enrol($course, 'student');
        $scale = $generator->create_scale(['courseid' => $course->id, 'scale' => 'Bad,Good']);
        $journal = $generator->create_module('journal', ['course' => $course->id, 'grade' => -$scale->id]);
        $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $student->id,
            'rating' => 2,
            'teacher' => get_admin()->id,
            'timemarked' => self::STARTDATE,
            'mailed' => 0,
        ]);

        $newcourseid = $this->backup_and_restore($course, self::STARTDATE, true);

        $newscale = $DB->get_record('scale', ['courseid' => $newcourseid], '*', MUST_EXIST);
        $this->assertNotEquals($scale->id, $newscale->id);
        $restored = $DB->get_record('journal', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals(-$newscale->id, $restored->grade);
        $gradeitem = \grade_item::fetch(['itemmodule' => 'journal', 'iteminstance' => $restored->id, 'courseid' => $newcourseid]);
        $this->assertEquals($newscale->id, $gradeitem->scaleid);

        $entry = $DB->get_record('journal_entries', ['journal' => $restored->id], '*', MUST_EXIST);
        $this->assertEquals(1, $entry->mailed);
    }
}
