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
}
