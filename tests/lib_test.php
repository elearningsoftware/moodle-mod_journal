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

    /**
     * Resetting the journals of a course also removes the grades of the deleted entries.
     *
     * @covers ::journal_reset_userdata
     * @covers ::journal_reset_gradebook
     */
    public function test_reset_removes_grades(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $journalgenerator = $generator->get_plugin_generator('mod_journal');
        $course = $generator->create_course();
        $journal = $journalgenerator->create_instance(['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $journalgenerator->create_entry((object)[
            'journal' => $journal->id,
            'userid' => $student->id,
            'rating' => 80,
            'teacher' => $teacher->id,
            'timemarked' => time(),
        ]);
        journal_update_grades($DB->get_record('journal', ['id' => $journal->id]), $student->id);

        $grades = grade_get_grades($course->id, 'mod', 'journal', $journal->id, $student->id);
        $this->assertEquals(80, $grades->items[0]->grades[$student->id]->grade);

        journal_reset_userdata((object)['reset_journal' => true, 'courseid' => $course->id]);

        $grades = grade_get_grades($course->id, 'mod', 'journal', $journal->id, $student->id);
        $this->assertNull($grades->items[0]->grades[$student->id]->grade);
    }

    /**
     * A grader must not be served files of an entry of a student in another separate group.
     *
     * @covers ::journal_pluginfile
     */
    public function test_pluginfile_rejects_entry_outside_graders_groups(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $journalgenerator = $generator->get_plugin_generator('mod_journal');

        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $journal = $journalgenerator->create_instance(['course' => $course->id]);
        $groupa = $generator->create_group(['courseid' => $course->id]);
        $groupb = $generator->create_group(['courseid' => $course->id]);

        $teacher = $generator->create_and_enrol($course, 'teacher');
        $student = $generator->create_and_enrol($course, 'student');
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $student->id]);

        $entryid = $journalgenerator->create_entry((object)['journal' => $journal->id, 'userid' => $student->id]);

        $cm = get_coursemodule_from_instance('journal', $journal->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $this->setUser($teacher);
        foreach (['entry', 'feedback'] as $filearea) {
            $this->assertFalse(journal_pluginfile($course, $cm, $context, $filearea, [$entryid, 'file.txt'], false));
        }
    }

    /**
     * A journal whose course module no longer exists can still be deleted.
     *
     * @covers ::journal_delete_instance
     */
    public function test_delete_instance_without_course_module(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/journal/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $journal = $this->getDataGenerator()->get_plugin_generator('mod_journal')->create_instance(['course' => $course->id]);
        $DB->delete_records('course_modules', ['id' => $journal->cmid]);

        $this->assertTrue(journal_delete_instance($journal->id));
        $this->assertFalse($DB->record_exists('journal', ['id' => $journal->id]));
    }

    /**
     * The feedback is passed to the gradebook as HTML, whatever the format of the entry text.
     *
     * @covers ::journal_get_user_grades
     */
    public function test_get_user_grades_feedback_format(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/journal/lib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $journal = $generator->get_plugin_generator('mod_journal')->create_instance(['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $generator->get_plugin_generator('mod_journal')->create_entry((object)[
            'journal' => $journal->id,
            'userid' => $student->id,
            'format' => FORMAT_PLAIN,
            'rating' => 50,
            'entrycomment' => '<p>Well done</p>',
        ]);

        $grades = journal_get_user_grades($DB->get_record('journal', ['id' => $journal->id]), $student->id);
        $this->assertEquals(FORMAT_HTML, $grades[$student->id]->feedbackformat);
        $this->assertEquals('<p>Well done</p>', $grades[$student->id]->feedback);
    }

    /**
     * Nothing is served for a file that does not exist in the entry of the student.
     *
     * @covers ::journal_pluginfile
     */
    public function test_pluginfile_missing_file(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $journal = $generator->get_plugin_generator('mod_journal')->create_instance(['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $entryid = $generator->get_plugin_generator('mod_journal')->create_entry((object)[
            'journal' => $journal->id,
            'userid' => $student->id,
        ]);

        $cm = get_coursemodule_from_instance('journal', $journal->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $this->setUser($student);
        foreach (['entry', 'feedback'] as $filearea) {
            $this->assertFalse(journal_pluginfile($course, $cm, $context, $filearea, [$entryid, 'missing.txt'], false));
        }
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Feedback of an overridden grade must not break out of the hidden input attribute.
     *
     * @covers ::journal_print_user_entry
     */
    public function test_print_user_entry_escapes_overridden_feedback(): void {
        $input = $this->render_overridden_feedback_input('" onfocus=alert(document.domain) autofocus x="');

        $this->assertFalse($input->hasAttribute('onfocus'));
        $this->assertFalse($input->hasAttribute('autofocus'));
        $this->assertStringContainsString('" onfocus=alert(document.domain) autofocus x="', $input->getAttribute('value'));
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Legitimate feedback of an overridden grade is kept in the hidden input.
     *
     * @covers ::journal_print_user_entry
     */
    public function test_print_user_entry_keeps_overridden_feedback(): void {
        $input = $this->render_overridden_feedback_input('<p>Gut gemacht & weiter so, Übung: <strong>5/5</strong></p>');

        $this->assertSame('<p>Gut gemacht &amp; weiter so, Übung: <strong>5/5</strong></p>', $input->getAttribute('value'));
    }

    /**
     * Renders a graded entry whose grade is overridden in the gradebook and returns the hidden feedback input.
     *
     * @param string $feedback Feedback of the entry.
     * @return \DOMElement
     */
    private function render_overridden_feedback_input(string $feedback): \DOMElement {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/mod/journal/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $journal = $generator->get_plugin_generator('mod_journal')->create_instance(['course' => $course->id]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $entryid = $generator->get_plugin_generator('mod_journal')->create_entry((object)[
            'journal' => $journal->id,
            'userid' => $student->id,
            'format' => FORMAT_HTML,
            'modified' => time() - 10,
            'rating' => 50,
            'entrycomment' => $feedback,
            'teacher' => $teacher->id,
            'timemarked' => time(),
        ]);
        journal_update_grades($DB->get_record('journal', ['id' => $journal->id]), $student->id);
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'journal',
            'iteminstance' => $journal->id,
            'courseid' => $course->id,
        ]);
        \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $student->id])->set_overridden(true);

        $this->setUser($teacher);
        $cm = get_coursemodule_from_instance('journal', $journal->id, $course->id, false, MUST_EXIST);
        // The Atto editor (default before Moodle 4.2) reads the page URL.
        $PAGE->set_url('/mod/journal/report.php', ['id' => $cm->id]);
        $PAGE->set_context(\context_module::instance($cm->id));
        $entry = $DB->get_record('journal_entries', ['id' => $entryid]);
        ob_start();
        journal_print_user_entry($course, $student, $entry, [], make_grades_menu(100), $cm->id);
        $html = ob_get_clean();

        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR);
        foreach ($dom->getElementsByTagName('input') as $input) {
            if ($input->getAttribute('name') === 'c' . $entryid) {
                return $input;
            }
        }
        $this->fail('Hidden feedback input not found.');
    }

    /**
     * Only no grade and the grades of the grade menu of a journal are valid.
     *
     * @covers ::journal_is_valid_grade
     */
    public function test_is_valid_grade(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');
        $this->resetAfterTest();

        $points = (object) ['grade' => 10];
        $this->assertTrue(journal_is_valid_grade($points, -1));
        $this->assertTrue(journal_is_valid_grade($points, 0));
        $this->assertTrue(journal_is_valid_grade($points, 10));
        $this->assertFalse(journal_is_valid_grade($points, 11));
        $this->assertFalse(journal_is_valid_grade($points, -2));

        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Bad,Good']);
        $scaled = (object) ['grade' => -$scale->id];
        $this->assertTrue(journal_is_valid_grade($scaled, -1));
        $this->assertTrue(journal_is_valid_grade($scaled, 1));
        $this->assertTrue(journal_is_valid_grade($scaled, 2));
        $this->assertFalse(journal_is_valid_grade($scaled, 0));
        $this->assertFalse(journal_is_valid_grade($scaled, 3));

        $nograde = (object) ['grade' => 0];
        $this->assertTrue(journal_is_valid_grade($nograde, -1));
        $this->assertFalse(journal_is_valid_grade($nograde, 0));
    }

    /**
     * Journals in weekly courses with days available are open from the start of the week of their section.
     *
     * @covers ::journal_get_editing_period
     * @covers ::journal_is_open
     */
    public function test_editing_period(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $startdate = make_timestamp(2026, 1, 5);
        $weekly = $generator->create_course(['format' => 'weeks', 'startdate' => $startdate, 'numsections' => 3]);
        $journal = $generator->create_module('journal', ['course' => $weekly->id, 'section' => 2, 'days' => 3]);
        $cm = get_coursemodule_from_instance('journal', $journal->id);

        $timestart = $startdate + WEEKSECS;
        $timefinish = $timestart + 3 * DAYSECS;
        $this->assertEquals([$timestart, $timefinish], journal_get_editing_period($journal, $weekly, $cm));
        $this->assertFalse(journal_is_open($journal, $weekly, $cm, $timestart - 1));
        $this->assertTrue(journal_is_open($journal, $weekly, $cm, $timestart));
        $this->assertTrue(journal_is_open($journal, $weekly, $cm, $timefinish - 1));
        $this->assertFalse(journal_is_open($journal, $weekly, $cm, $timefinish));

        // Without days available, or in other course formats, there is no editing period.
        $alwaysopen = $generator->create_module('journal', ['course' => $weekly->id, 'section' => 2, 'days' => 0]);
        $alwaysopencm = get_coursemodule_from_instance('journal', $alwaysopen->id);
        $this->assertNull(journal_get_editing_period($alwaysopen, $weekly, $alwaysopencm));
        $topics = $generator->create_course(['format' => 'topics', 'startdate' => $startdate]);
        $topicsjournal = $generator->create_module('journal', ['course' => $topics->id, 'section' => 2, 'days' => 3]);
        $topicscm = get_coursemodule_from_instance('journal', $topicsjournal->id);
        $this->assertNull(journal_get_editing_period($topicsjournal, $topics, $topicscm));
        $this->assertTrue(journal_is_open($topicsjournal, $topics, $topicscm, $startdate - YEARSECS));
    }
}
