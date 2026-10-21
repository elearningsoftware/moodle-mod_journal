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
 * Tests for the opening and closing times of journals.
 *
 * @package    mod_journal
 * @category   test
 * @copyright  2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_journal\dates
 */
final class dates_test extends \advanced_testcase {
    /**
     * Load the journal library.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');
        require_once($CFG->dirroot . '/calendar/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Returns the calendar events of a journal by event type.
     *
     * @param int $journalid Journal ID
     * @return array Events by event type
     */
    private function get_events(int $journalid): array {
        global $DB;
        $events = $DB->get_records('event', ['modulename' => 'journal', 'instance' => $journalid]);
        return array_column($events, null, 'eventtype');
    }

    /**
     * The opening and closing times limit the editing period, in any course format.
     *
     * @covers ::journal_get_editing_period
     * @covers ::journal_is_open
     */
    public function test_editing_period(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $journal = $this->getDataGenerator()->create_module('journal', [
            'course' => $course->id,
            'days' => 7,
            'timeopen' => 1000,
            'timeclose' => 2000,
        ]);
        $cm = get_coursemodule_from_instance('journal', $journal->id);

        $this->assertEquals([1000, 2000], journal_get_editing_period($journal, $course, $cm));
        $this->assertFalse(journal_is_open($journal, $course, $cm, 999));
        $this->assertTrue(journal_is_open($journal, $course, $cm, 1000));
        $this->assertTrue(journal_is_open($journal, $course, $cm, 1999));
        $this->assertFalse(journal_is_open($journal, $course, $cm, 2000));

        // Either time can be left out.
        $journal->timeclose = 0;
        $this->assertEquals([1000, 0], journal_get_editing_period($journal, $course, $cm));
        $this->assertTrue(journal_is_open($journal, $course, $cm, PHP_INT_MAX));
        $journal->timeopen = 0;
        $journal->timeclose = 2000;
        $this->assertTrue(journal_is_open($journal, $course, $cm, 0));
        $this->assertFalse(journal_is_open($journal, $course, $cm, 2000));
    }

    /**
     * Calendar events follow the opening and closing times of a journal.
     *
     * @covers ::journal_set_events
     * @covers ::journal_delete_instance
     */
    public function test_calendar_events(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $timeopen = time() + DAYSECS;
        $timeclose = time() + WEEKSECS;
        $journal = $this->getDataGenerator()->create_module('journal', [
            'course' => $course->id,
            'name' => 'Diary',
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
        ]);

        $events = $this->get_events($journal->id);
        $this->assertEqualsCanonicalizing([JOURNAL_EVENT_TYPE_OPEN, JOURNAL_EVENT_TYPE_CLOSE], array_keys($events));
        $this->assertEquals($timeopen, $events[JOURNAL_EVENT_TYPE_OPEN]->timestart);
        $this->assertEquals(CALENDAR_EVENT_TYPE_STANDARD, $events[JOURNAL_EVENT_TYPE_OPEN]->type);
        $this->assertEquals('Diary opens', $events[JOURNAL_EVENT_TYPE_OPEN]->name);
        $this->assertEquals($timeclose, $events[JOURNAL_EVENT_TYPE_CLOSE]->timesort);
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, $events[JOURNAL_EVENT_TYPE_CLOSE]->type);

        // Without a closing time, the opening is shown on the timeline.
        $record = $DB->get_record('journal', ['id' => $journal->id]);
        $record->timeclose = 0;
        $record->timeopen = $timeopen + HOURSECS;
        $DB->update_record('journal', $record);
        journal_refresh_events($course->id, $record->id);
        $events = $this->get_events($journal->id);
        $this->assertEquals([JOURNAL_EVENT_TYPE_OPEN], array_keys($events));
        $this->assertEquals($timeopen + HOURSECS, $events[JOURNAL_EVENT_TYPE_OPEN]->timestart);
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, $events[JOURNAL_EVENT_TYPE_OPEN]->type);

        course_delete_module($journal->cmid);
        $this->assertEmpty($this->get_events($journal->id));
    }

    /**
     * Students without an entry get an action on their timeline while the journal can be edited.
     *
     * @covers ::mod_journal_core_calendar_provide_event_action
     */
    public function test_event_action(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $journal = $generator->create_module('journal', [
            'course' => $course->id,
            'timeopen' => time() + DAYSECS,
            'timeclose' => time() + WEEKSECS,
        ]);
        $factory = new \core_calendar\action_factory();
        $eventid = $this->get_events($journal->id)[JOURNAL_EVENT_TYPE_CLOSE]->id;

        // Not open yet: the action is shown but cannot be taken.
        $action = mod_journal_core_calendar_provide_event_action(\calendar_event::load($eventid), $factory, $student->id);
        $this->assertNotNull($action);
        $this->assertFalse($action->is_actionable());
        $this->assertEquals(new \moodle_url('/mod/journal/view.php', ['id' => $journal->cmid]), $action->get_url());

        // Open.
        $DB->set_field('journal', 'timeopen', time() - DAYSECS, ['id' => $journal->id]);
        rebuild_course_cache($course->id, true);
        $action = mod_journal_core_calendar_provide_event_action(\calendar_event::load($eventid), $factory, $student->id);
        $this->assertTrue($action->is_actionable());

        // Teachers do not write entries.
        $this->assertNull(mod_journal_core_calendar_provide_event_action(\calendar_event::load($eventid), $factory, $teacher->id));

        // No action is left once the student has written an entry.
        $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $student->id,
        ]);
        $this->assertNull(mod_journal_core_calendar_provide_event_action(\calendar_event::load($eventid), $factory, $student->id));

        // Closed.
        $DB->delete_records('journal_entries', ['journal' => $journal->id]);
        $DB->set_field('journal', 'timeclose', time() - 1, ['id' => $journal->id]);
        rebuild_course_cache($course->id, true);
        $this->assertNull(mod_journal_core_calendar_provide_event_action(\calendar_event::load($eventid), $factory, $student->id));
    }

    /**
     * The opening and closing dates are shown for the activity.
     */
    public function test_activity_dates(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $timeopen = time() - DAYSECS;
        $timeclose = time() + DAYSECS;
        $journal = $this->getDataGenerator()->create_module('journal', [
            'course' => $course->id,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
        ]);
        $alwaysopen = $this->getDataGenerator()->create_module('journal', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);

        $dates = \core\activity_dates::get_dates_for_module($modinfo->get_cm($journal->cmid), $user->id);
        $this->assertEquals([
            [
                'dataid' => 'timeopen',
                'label' => get_string('activitydate:opened', 'course'),
                'timestamp' => $timeopen,
            ],
            [
                'dataid' => 'timeclose',
                'label' => get_string('activitydate:closes', 'course'),
                'timestamp' => $timeclose,
            ],
        ], $dates);
        $this->assertEmpty(\core\activity_dates::get_dates_for_module($modinfo->get_cm($alwaysopen->cmid), $user->id));
    }

    /**
     * Resetting a course with a new start date moves the opening and closing times and their events.
     *
     * @covers ::journal_reset_userdata
     */
    public function test_reset_shifts_dates(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $journal = $this->getDataGenerator()->create_module('journal', [
            'course' => $course->id,
            'timeopen' => 100000,
            'timeclose' => 200000,
        ]);

        journal_reset_userdata((object) ['courseid' => $course->id, 'timeshift' => WEEKSECS]);

        $record = $DB->get_record('journal', ['id' => $journal->id]);
        $this->assertEquals(100000 + WEEKSECS, $record->timeopen);
        $this->assertEquals(200000 + WEEKSECS, $record->timeclose);
        $events = $this->get_events($journal->id);
        $this->assertEquals(100000 + WEEKSECS, $events[JOURNAL_EVENT_TYPE_OPEN]->timestart);
        $this->assertEquals(200000 + WEEKSECS, $events[JOURNAL_EVENT_TYPE_CLOSE]->timestart);
    }
}
