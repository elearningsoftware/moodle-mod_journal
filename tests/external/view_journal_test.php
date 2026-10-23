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

namespace mod_journal\external;

use advanced_testcase;
use coding_exception;
use dml_exception;
use moodle_exception;

/**
 * Unit tests for the class \mod_journal\external\view_journal.
 *
 * Guards bug B1: the WS used to require the non-existent
 * capability 'mod/journal:view' which caused every call to throw
 * required_capability_exception, breaking the official Moodle
 * Mobile App integration.
 *
 * @package   mod_journal
 * @copyright 2025 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_journal\external\view_journal
 */
final class view_journal_test extends advanced_testcase {
    /**
     * A student with addentries can open the journal via the WS.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers ::execute
     */
    public function test_student_can_view_journal(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        $this->setUser($student);

        // Before the fix this threw required_capability_exception because
        // the WS required 'mod/journal:view' which is not defined in
        // db/access.php.
        $result = view_journal::execute($journal->cmid);
        $this->assertNull($result);
    }

    /**
     * A teacher can also open the journal via the WS.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers ::execute
     */
    public function test_teacher_can_view_journal(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        $this->setUser($teacher);

        $result = view_journal::execute($journal->cmid);
        $this->assertNull($result);
    }

    /**
     * A user with no relation to the course cannot view the journal.
     *
     * Non-enrolled users are stopped by require_login() before the
     * capability check ever runs, so the WS rejects them with
     * require_login_exception rather than required_capability_exception.
     * Either exception is a valid "access denied" outcome.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers ::execute
     */
    public function test_unrelated_user_is_rejected(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);

        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);

        // Moodle 4.5+ aliases require_login_exception into \core\exception;
        // older Moodles (down to the declared 4.0 minimum) only have the
        // global class. Reference both via FQCN string so the test passes
        // on every supported branch.
        $this->expectException(
            class_exists('\\core\\exception\\require_login_exception')
                ? \core\exception\require_login_exception::class
                : \require_login_exception::class
        );
        view_journal::execute($journal->cmid);
    }

    /**
     * A manager, who can grade entries but not write any, can open the journal via the WS like on the web.
     *
     * @return void
     * @covers ::execute
     */
    public function test_manager_can_view_journal(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $manager = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $journal = $this->getDataGenerator()->create_module('journal', ['course' => $course]);
        $this->assertFalse(has_capability('mod/journal:addentries', \context_module::instance($journal->cmid), $manager));

        $this->setUser($manager);
        $this->assertNull(view_journal::execute($journal->cmid));
    }
}
