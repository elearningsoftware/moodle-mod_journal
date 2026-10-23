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

namespace mod_journal\courseformat;

/**
 * Tests for the course activities overview of journals, in Moodle 5.0 and later.
 *
 * @package   mod_journal
 * @category  test
 * @copyright 2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\courseformat\overview
 */
final class overview_test extends \advanced_testcase {
    /**
     * Skip the tests before Moodle 5.0, which has no course activities overview.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists(\core_courseformat\local\overview\overviewfactory::class)) {
            $this->markTestSkipped('The course activities overview needs Moodle 5.0 or later.');
        }
        $this->resetAfterTest();
    }

    /**
     * Returns the extra overview items of a journal for the current user.
     *
     * @param \stdClass $course The course
     * @param \stdClass $journal The journal
     * @return array The overview items, by name
     */
    private function get_items(\stdClass $course, \stdClass $journal): array {
        $cm = get_fast_modinfo($course)->get_cm($journal->cmid);
        return \core_courseformat\local\overview\overviewfactory::create($cm)->get_extra_overview_items();
    }

    /**
     * Teachers see how many students wrote an entry and how many entries need grading.
     *
     * @covers ::get_extra_overview_items
     */
    public function test_teacher_items(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $students = [
            $generator->create_and_enrol($course, 'student'),
            $generator->create_and_enrol($course, 'student'),
            $generator->create_and_enrol($course, 'student'),
        ];
        $journal = $generator->create_module('journal', ['course' => $course]);
        $journalgenerator = $generator->get_plugin_generator('mod_journal');
        $journalgenerator->create_entry((object) ['journal' => $journal->id, 'userid' => $students[0]->id]);
        $journalgenerator->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $students[1]->id,
            'modified' => time() - HOURSECS,
            'rating' => 50,
            'teacher' => $teacher->id,
            'timemarked' => time(),
        ]);

        $this->setUser($teacher);
        $items = $this->get_items($course, $journal);

        $this->assertEquals(2, $items['studentswhowrote']->get_value());
        $this->assertEquals(
            get_string('count_of_total', 'core', ['count' => 2, 'total' => 3]),
            $items['studentswhowrote']->get_content()->text
        );
        $this->assertEquals(1, $items['needgrading']->get_value());
        $this->assertNull($items['write']);
    }

    /**
     * Students see whether they wrote an entry, and nothing about the entries of others.
     *
     * @covers ::get_extra_overview_items
     */
    public function test_student_items(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $other = $generator->create_and_enrol($course, 'student');
        $journal = $generator->create_module('journal', ['course' => $course]);
        $generator->get_plugin_generator('mod_journal')->create_entry((object) [
            'journal' => $journal->id,
            'userid' => $student->id,
        ]);

        $this->setUser($student);
        $items = $this->get_items($course, $journal);
        $this->assertNull($items['studentswhowrote']);
        $this->assertNull($items['needgrading']);
        $this->assertTrue($items['write']->get_value());

        $this->setUser($other);
        $this->assertFalse($this->get_items($course, $journal)['write']->get_value());
    }
}
