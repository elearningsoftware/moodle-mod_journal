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

/**
 * Behat steps for mod_journal.
 *
 * @package    mod_journal
 * @category   test
 * @copyright  2025 eDaktik GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;

/**
 * Steps definitions for journal.
 *
 * @package    mod_journal
 * @category   test
 * @copyright  2025 eDaktik GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_journal extends behat_base {
    /**
     * Checks the completion state of the journal activity compatible with multiple Moodle versions.
     *
     * @param string $activityname The name of the activity.
     * @param string $state The expected state (complete|not complete).
     *
     * @Then /^the journal activity "(?P<activityname>(?:[^"]|\\")*)" should be marked as (?P<state>complete|not complete)$/
     */
    public function the_journal_activity_should_be_marked_as($activityname, $state) {
        // New completion UI (Badges).
        // "not complete" maps to "todo".
        // "complete" maps to "done".
        $completionstatus = ($state === 'complete') ? 'done' : 'todo';
        $conditionname = get_string('completiondetail:completion_create_entry', 'mod_journal');

        // We need to execute the step:
        // the :conditionname completion condition of :activityname is displayed as :completionstatus.
        $this->execute('behat_completion::activity_completion_condition_displayed_as', [
            $conditionname, $activityname, $completionstatus,
        ]);
    }

    /**
     * Overrides the grade of a user in a journal in the gradebook.
     *
     * @param string $username The username of the user, who must already have a grade.
     * @param string $idnumber The ID number of the journal.
     *
     * @Given /^the grade of "(?P<username>(?:[^"]|\\")*)" in the journal "(?P<idnumber>(?:[^"]|\\")*)" is overridden$/
     */
    public function the_grade_in_the_journal_is_overridden($username, $idnumber) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'journal',
            'iteminstance' => $cm->instance,
            'courseid' => $cm->course,
        ]);
        \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid])->set_overridden(true);
    }
}
