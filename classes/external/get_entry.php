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
use external_single_structure;
use external_value;
use invalid_parameter_exception;

global $CFG;

// Dynamic Class Logic.
// Ensures compatibility between Moodle 3.9 (global classes) and 4.0+ (core_external namespace).
if (!class_exists('mod_journal\external\journal_external_api_base')) {
    if (class_exists('core_external\external_api')) {
        // Moodle 4.0 and higher.
        class_alias('core_external\external_api', 'mod_journal\external\journal_external_api_base');

        // Ensure global class aliases exist for the helper types used in this file.
        // This is necessary because "use external_function_parameters;" expects the global class,
        // which might not be aliased automatically in strict PHPUnit isolation.
        if (!class_exists('external_function_parameters')) {
            class_alias('core_external\external_function_parameters', 'external_function_parameters');
        }
        if (!class_exists('external_value')) {
            class_alias('core_external\external_value', 'external_value');
        }
        if (!class_exists('external_single_structure')) {
            class_alias('core_external\external_single_structure', 'external_single_structure');
        }
    } else {
        // Moodle 3.9 - 3.11.
        require_once($CFG->libdir . '/externallib.php');
        class_alias('external_api', 'mod_journal\external\journal_external_api_base');
    }
}

/**
 * External function to get a journal entry.
 *
 * @package   mod_journal
 * @copyright 2025 eDaktik GmbH {@link https://www.edaktik.at/}
 * @author    Christian Abila <christian.abila@edaktik.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_entry extends journal_external_api_base {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'journalid' => new external_value(PARAM_INT, 'course module id of journal'),
            ]
        );
    }

    /**
     * Return one entry record from the database, including contents optionally.
     *
     * @param int $journalid Journal course module id
     * @return array of warnings and the entries
     * @since Moodle 3.3
     * @throws invalid_parameter_exception
     */
    public static function execute(int $journalid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['journalid' => $journalid]);

        if (!($cm = get_coursemodule_from_id('journal', $params['journalid']))) {
            throw new invalid_parameter_exception(get_string('incorrectcmid', 'journal'));
        }

        if (!$DB->record_exists('course', ['id' => $cm->course])) {
            throw new invalid_parameter_exception(get_string('incorrectcourseid', 'journal'));
        }

        if (!($journal = $DB->get_record('journal', ['id' => $cm->instance]))) {
            throw new invalid_parameter_exception(get_string('incorrectjournalid', 'journal'));
        }

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/journal:addentries', $context);

        if ($entry = $DB->get_record('journal_entries', ['userid' => $USER->id, 'journal' => $journal->id])) {
            return [
                'text' => (string) $entry->text,
                'modified' => $entry->modified,
                'rating' => (float) $entry->rating,
                'comment' => (string) $entry->entrycomment,
                'teacher' => $entry->teacher,
            ];
        }

        return [
            'text' => '',
            'modified' => 0,
            'rating' => -1.0,
            'comment' => '',
            'teacher' => 0,
        ];
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'text' => new external_value(PARAM_RAW, 'journal text'),
                'modified' => new external_value(PARAM_INT, 'last modified time'),
                'rating' => new external_value(PARAM_FLOAT, 'teacher rating'),
                'comment' => new external_value(PARAM_RAW, 'teacher comment'),
                'teacher' => new external_value(PARAM_INT, 'id of teacher'),
            ]
        );
    }
}
