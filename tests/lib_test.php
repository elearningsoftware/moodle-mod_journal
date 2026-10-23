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

    /**
     * The individual save button is not shown for a grade overridden in the gradebook.
     *
     * @covers ::journal_print_user_entry
     */
    public function test_print_user_entry_hides_save_button_of_overridden_grade(): void {
        $input = $this->render_overridden_feedback_input('<p>Good</p>');

        $xpath = new \DOMXPath($input->ownerDocument);
        $this->assertCount(0, $xpath->query('//input[contains(@class, "saveindividualfeedback")]'));
    }

    /**
     * Entries have feedback to show if they have a grade, including 0, or a feedback text.
     *
     * @covers ::journal_entry_has_feedback
     */
    public function test_entry_has_feedback(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');

        $this->assertFalse(journal_entry_has_feedback(null));
        $this->assertFalse(journal_entry_has_feedback((object) ['rating' => -1, 'entrycomment' => '']));
        $this->assertFalse(journal_entry_has_feedback((object) ['rating' => null, 'entrycomment' => null]));
        $this->assertTrue(journal_entry_has_feedback((object) ['rating' => '0', 'entrycomment' => '']));
        $this->assertTrue(journal_entry_has_feedback((object) ['rating' => 5, 'entrycomment' => null]));
        $this->assertTrue(journal_entry_has_feedback((object) ['rating' => -1, 'entrycomment' => '<p>Good</p>']));
    }

    /**
     * Words are counted across paragraphs and line breaks, and Chinese and Japanese characters one by one.
     *
     * @covers ::journal_count_words
     * @covers ::journal_get_printable_count
     */
    public function test_count_words(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');

        $this->assertSame(0, journal_count_words(''));
        $this->assertSame(3, journal_count_words('<p>One</p><p>two<br>three</p>'));
        $this->assertSame(2, journal_count_words('One <b>two</b>'));
        $this->assertSame(4, journal_count_words('<p>日本語文</p>'));
        $this->assertEquals('3 words', journal_get_printable_count('<p>One</p><p>two</p><p>three</p>'));
        $this->assertEquals('4 characters', journal_get_printable_count('<p>日本語文</p>'));
    }

    /**
     * The notify teachers setting keeps valid values, turns an unchecked setting off and other values on.
     *
     * @covers ::journal_clean_notify_teachers
     */
    public function test_clean_notify_teachers(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');

        $this->assertSame(manager::NOTIFY_NEVER, journal_clean_notify_teachers(null));
        $this->assertSame(manager::NOTIFY_NEVER, journal_clean_notify_teachers('0'));
        $this->assertSame(manager::NOTIFY_ALWAYS, journal_clean_notify_teachers('1'));
        $this->assertSame(manager::NOTIFY_NEEDSGRADING, journal_clean_notify_teachers('2'));
        $this->assertSame(manager::NOTIFY_ALWAYS, journal_clean_notify_teachers(7));
    }

    /**
     * Texts are converted to plain text the same way whatever their format, and plain text converts to itself.
     *
     * @covers ::journal_get_plain_text
     */
    public function test_get_plain_text(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');
        $this->resetAfterTest();
        $context = \context_system::instance();

        $this->assertSame(
            "Hello world\n\nSecond & paragraph\nnext line",
            journal_get_plain_text('<p>Hello <b>world</b></p><p>Second &amp; paragraph<br>next line</p>', FORMAT_HTML, $context)
        );
        $this->assertSame("Line one\nLine two", journal_get_plain_text("Line one\r\nLine two\n", FORMAT_MOODLE, $context));
        $this->assertSame('', journal_get_plain_text(null, FORMAT_HTML, $context));

        $plain = "A <b> & \"quoted\" text\nnext line";
        $this->assertSame($plain, journal_get_plain_text($plain, FORMAT_PLAIN, $context));
        // Plain text feedback is saved as HTML with line breaks.
        $this->assertSame($plain, journal_get_plain_text(nl2br(s($plain)), FORMAT_HTML, $context));
    }

    /**
     * Sorting by date puts the users without an entry last.
     *
     * @covers ::mod_journal_sort_users
     */
    public function test_sort_users(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');

        $users = [];
        foreach ([1 => 'Carl', 2 => 'Anna', 3 => 'Bert', 4 => 'Dora'] as $id => $firstname) {
            $users[$id] = (object) ['id' => $id, 'firstname' => $firstname, 'lastname' => 'Doe'];
        }
        $entrybyuser = [
            2 => (object) ['modified' => 200],
            4 => (object) ['modified' => 100],
        ];

        mod_journal_sort_users($users, 'dateasc', $entrybyuser);
        $this->assertSame([4, 2], array_slice(array_keys($users), 0, 2));
        $this->assertEqualsCanonicalizing([1, 3], array_slice(array_keys($users), 2));

        mod_journal_sort_users($users, 'datedesc', $entrybyuser);
        $this->assertSame([2, 4], array_slice(array_keys($users), 0, 2));
        $this->assertEqualsCanonicalizing([1, 3], array_slice(array_keys($users), 2));

        mod_journal_sort_users($users, 'firstnamedesc', $entrybyuser);
        $this->assertSame([4, 1, 3, 2], array_keys($users));
    }

    /**
     * Entries graded before the cut-off time are returned once, unless they have neither a grade nor feedback.
     *
     * @covers ::journal_get_unmailed_graded
     */
    public function test_get_unmailed_graded(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $journalgenerator = $generator->get_plugin_generator('mod_journal');
        $course = $generator->create_course();
        $journal = $journalgenerator->create_instance(['course' => $course->id]);
        $now = time();
        $entries = [
            'zero' => ['rating' => 0, 'entrycomment' => null],
            'comment' => ['rating' => -1, 'entrycomment' => '<p>Good</p>'],
            'blank' => ['rating' => -1, 'entrycomment' => '  '],
            'nothing' => ['rating' => -1, 'entrycomment' => null],
            'nullrating' => ['rating' => null, 'entrycomment' => ''],
            'mailed' => ['rating' => 5, 'entrycomment' => null, 'mailed' => 1],
            'recent' => ['rating' => 5, 'entrycomment' => null, 'timemarked' => $now],
        ];
        $ids = [];
        foreach ($entries as $name => $entry) {
            $ids[$name] = $journalgenerator->create_entry((object) ($entry + [
                'journal' => $journal->id,
                'userid' => $generator->create_user()->id,
                'teacher' => get_admin()->id,
                'timemarked' => $now - HOURSECS,
            ]));
        }

        $this->assertEqualsCanonicalizing(
            [$ids['zero'], $ids['comment']],
            array_keys(journal_get_unmailed_graded($now - MINSECS))
        );
    }
}
