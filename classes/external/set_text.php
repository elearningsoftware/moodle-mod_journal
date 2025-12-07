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

use coding_exception;
use context_module;
use dml_exception;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;
use mod_journal\event\entry_created;
use mod_journal\event\entry_updated;
use required_capability_exception;
use stdClass;

global $CFG;

// Dynamic Class Logic.
// Ensures compatibility between Moodle 3.9 (global classes) and 4.0+ (core_external namespace).
if (!class_exists('mod_journal\external\journal_external_api_base')) {
    if (class_exists('core_external\external_api')) {
        // Moodle 4.0 and higher.
        class_alias('core_external\external_api', 'mod_journal\external\journal_external_api_base');

        // Ensure global class aliases exist for the helper types used in this file.
        if (!class_exists('external_function_parameters')) {
            class_alias('core_external\external_function_parameters', 'external_function_parameters');
        }
        if (!class_exists('external_value')) {
            class_alias('core_external\external_value', 'external_value');
        }
    } else {
        // Moodle 3.9 - 3.11.
        require_once($CFG->libdir . '/externallib.php');
        class_alias('external_api', 'mod_journal\external\journal_external_api_base');
    }
}

/**
 * External function to set a journal's text
 *
 * @package   mod_journal
 * @copyright 2025 eDaktik GmbH {@link https://www.edaktik.at/}
 * @author    Christian Abila <christian.abila@edaktik.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_text extends journal_external_api_base {
    /**
     * Returns description of method parameters
     *
     * @since Moodle 3.3
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'journalid' => new external_value(PARAM_INT, 'course module id of journal'),
                'text' => new external_value(PARAM_RAW, 'text to set'),
                'format' => new external_value(PARAM_INT, 'format of text'),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_value
     * @since Moodle 3.3
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_RAW, 'new text');
    }

    /**
     * Sets the text for the element
     *
     * @param int $journalid Journal course module ID
     * @param string $text Text parameter
     * @param int|string $format Format constant for the string
     * @return string
     * @throws invalid_parameter_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public static function execute(int $journalid, string $text, $format) {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['journalid' => $journalid, 'text' => $text, 'format' => $format]
        );

        if (!$cm = get_coursemodule_from_id('journal', $params['journalid'])) {
            throw new invalid_parameter_exception(get_string('incorrectcmid', 'journal'));
        }

        if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
            throw new invalid_parameter_exception(get_string('incorrectcourseid', 'journal'));
        }

        if (!$journal = $DB->get_record('journal', ['id' => $cm->instance])) {
            throw new invalid_parameter_exception(get_string('incorrectjournalid', 'journal'));
        }

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/journal:addentries', $context);

        $entry = $DB->get_record('journal_entries', ['userid' => $USER->id, 'journal' => $journal->id]);

        $timenow = time();
        $newentry = new stdClass();
        $newentry->text = $params['text'];
        $newentry->format = $params['format'];
        $newentry->modified = $timenow;

        if ($entry) {
            $newentry->id = $entry->id;
            $DB->update_record('journal_entries', $newentry);
        } else {
            $newentry->userid = $USER->id;
            $newentry->journal = $journal->id;
            $newentry->id = $DB->insert_record('journal_entries', $newentry);
        }

        if ($entry) {
            // Trigger module entry updated event.
            $event = entry_updated::create([
                'objectid' => $journal->id,
                'context' => $context,
            ]);
        } else {
            // Trigger module entry created event.
            $event = entry_created::create([
                'objectid' => $journal->id,
                'context' => $context,
            ]);
        }
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('journal', $journal);
        $event->trigger();

        return $newentry->text;
    }
}
