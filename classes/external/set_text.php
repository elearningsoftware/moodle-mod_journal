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

use coding_exception;
use context_module;
use dml_exception;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use invalid_parameter_exception;
use mod_journal\manager;
use required_capability_exception;

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
     * Returns description of method parameters.
     *
     * @since Moodle 3.3
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'journalid' => new external_value(PARAM_INT, 'journal instance id, ignored if cmid is given'),
                'text' => new external_value(PARAM_RAW, 'text to set'),
                'format' => new external_value(PARAM_INT, 'format of text'),
                'itemid' => new external_value(PARAM_INT, 'Draft item id for files', VALUE_DEFAULT, 0),
                'cmid' => new external_value(PARAM_INT, 'course module id of journal', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_ALPHA, 'status'),
                'text' => new external_value(PARAM_RAW, 'new text'),
            ]
        );
    }

    /**
     * Sets the text for the element.
     *
     * @param int $journalid Journal instance ID, ignored if $cmid is given
     * @param string $text Text parameter
     * @param int|string $format Format constant for the string
     * @param int $itemid Item id for draft area (files)
     * @param int $cmid Journal course module ID, as taken by mod_journal_get_entry
     * @return array
     */
    public static function execute(int $journalid, string $text, $format, int $itemid = 0, int $cmid = 0) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/journal/lib.php');

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['journalid' => $journalid, 'text' => $text, 'format' => $format, 'itemid' => $itemid, 'cmid' => $cmid]
        );

        if (!in_array($params['format'], [FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN, FORMAT_MARKDOWN])) {
            throw new invalid_parameter_exception('Invalid text format: ' . $params['format']);
        }

        if ($params['cmid']) {
            $cm = get_coursemodule_from_id('journal', $params['cmid']);
        } else {
            $cm = get_coursemodule_from_instance('journal', $params['journalid']);
        }
        if (!$cm) {
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

        if (!journal_is_open($journal, $course, $cm)) {
            throw new \moodle_exception('journalclosed', 'journal');
        }

        $entry = manager::create_from_coursemodule($cm)->save_entry(
            $params['text'],
            $params['format'],
            $params['itemid']
        );

        // Return array matching execute_returns.
        return [
            'status' => 'ok',
            'text' => $entry->text,
        ];
    }
}
