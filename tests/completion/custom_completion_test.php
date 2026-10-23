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

namespace mod_journal\completion;

/**
 * Tests for the custom completion rules of journals.
 *
 * @package   mod_journal
 * @category  test
 * @copyright 2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\completion\custom_completion
 */
final class custom_completion_test extends \advanced_testcase {
    /**
     * Users complete the create entry rule once they have an entry.
     *
     * @covers ::get_state
     * @covers ::get_custom_rule_descriptions
     */
    public function test_create_entry_rule(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $other = $generator->create_and_enrol($course, 'student');
        $journal = $generator->create_module('journal', [
            'course' => $course,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completion_create_entry' => 1,
        ]);
        $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $student->id,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($journal->cmid);

        $completion = new custom_completion($cm, (int) $student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state('completion_create_entry'));
        $completion = new custom_completion($cm, (int) $other->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completion_create_entry'));
        $this->assertEquals(['completion_create_entry'], custom_completion::get_defined_custom_rules());
        $this->assertEquals(
            ['completion_create_entry' => get_string('completiondetail:completion_create_entry', 'journal')],
            $completion->get_custom_rule_descriptions()
        );
    }

    /**
     * The create entry rule cannot be checked in journals that do not use it.
     *
     * @covers ::get_state
     */
    public function test_create_entry_rule_disabled(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $journal = $generator->create_module('journal', [
            'course' => $course,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
            'completion_create_entry' => 0,
        ]);
        $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $student->id,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($journal->cmid);

        $completion = new custom_completion($cm, (int) $student->id);
        $this->expectException(\moodle_exception::class);
        $completion->get_state('completion_create_entry');
    }
}
