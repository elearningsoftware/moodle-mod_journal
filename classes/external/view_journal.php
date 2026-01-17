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

defined('MOODLE_INTERNAL') || die();

use context_module;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;

global $CFG;

// Dynamic Class Logic.
if (!class_exists('mod_journal\external\journal_external_api_base')) {
    if (class_exists('core_external\external_api')) {
        class_alias('core_external\external_api', 'mod_journal\external\journal_external_api_base');
        if (!class_exists('external_function_parameters')) {
            class_alias('core_external\external_function_parameters', 'external_function_parameters');
        }
        if (!class_exists('external_value')) {
            class_alias('core_external\external_value', 'external_value');
        }
    } else {
        require_once($CFG->libdir . '/externallib.php');
        class_alias('external_api', 'mod_journal\external\journal_external_api_base');
    }
}

/**
 * External function to trigger journal view event.
 *
 * @package   mod_journal
 * @copyright 2025 adrian.emanuel.sarmas@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_journal extends journal_external_api_base {
    /**
     * Parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'journalid' => new external_value(PARAM_INT, 'course module id'),
        ]);
    }

    /**
     * Execute.
     * @param int $journalid
     */
    public static function execute($journalid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['journalid' => $journalid]);
        $cm = get_coursemodule_from_id('journal', $params['journalid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $journal = $DB->get_record('journal', ['id' => $cm->instance], '*', MUST_EXIST);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/journal:view', $context);

        // Trigger event.
        $event = \mod_journal\event\course_module_viewed::create([
            'objectid' => $journal->id,
            'context' => $context,
        ]);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('journal', $journal);
        $event->trigger();

        // Completion.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        return null;
    }

    /**
     * Return.
     * @return null
     */
    public static function execute_returns() {
        return null;
    }
}
