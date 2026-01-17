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

require_once($CFG->libdir . '/externallib.php');


use context_module;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;

global $CFG;

// Dynamic Class Logic for compatibility.
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
 * External function to save feedback and grade for a journal entry.
 *
 * @package   mod_journal
 * @copyright 2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_feedback extends journal_external_api_base {
    /**
     * Parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'entryid' => new external_value(PARAM_INT, 'Journal entry id'),
            'userid' => new external_value(PARAM_INT, 'Student user id'),
            'grade' => new external_value(PARAM_INT, 'Grade (-1 = no grade)'),
            'feedback' => new external_value(PARAM_RAW, 'Feedback text', VALUE_DEFAULT, ''),
            'itemid' => new external_value(PARAM_INT, 'Draft item id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param int $entryid
     * @param int $userid
     * @param int $grade
     * @param string $feedback
     * @param int $itemid
     * @return array
     * @throws \coding_exception
     * @throws \core_external\restricted_context_exception
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     * @throws \moodle_exception
     * @throws \require_login_exception
     * @throws \required_capability_exception
     * @throws invalid_parameter_exception
     */
    public static function execute(
        int $cmid,
        int $entryid,
        int $userid,
        int $grade,
        string $feedback = '',
        int $itemid = 0
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'entryid' => $entryid,
            'userid' => $userid,
            'grade' => $grade,
            'feedback' => $feedback,
            'itemid' => $itemid,
        ]);

        $cm = get_coursemodule_from_id('journal', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_login($course, false, $cm);
        require_capability('mod/journal:manageentries', $context);

        $journal = $DB->get_record('journal', ['id' => $cm->instance], '*', MUST_EXIST);
        $journal->cmidnumber = $cm->idnumber;

        // Entry must exist and must match both journal + user.
        $entry = $DB->get_record('journal_entries', [
            'id' => $params['entryid'],
            'journal' => $journal->id,
            'userid' => $params['userid'],
        ], '*', MUST_EXIST);

        $newgrade = (int) $params['grade'];
        if ($newgrade < -1) {
            $newgrade = -1;
        }

        $newfeedback = clean_text((string) $params['feedback'], FORMAT_HTML);

        // Only attempt draft file save if itemid > 0.
        if (!empty($params['itemid'])) {
            $newfeedback = file_save_draft_area_files(
                $params['itemid'],
                $context->id,
                'mod_journal',
                'feedback',
                $entry->id,
                [],
                $newfeedback
            );
        }

        $changed = ((int) $entry->rating !== $newgrade) || ((string) $entry->entrycomment !== (string) $newfeedback);

        if ($changed) {
            $timenow = time();

            $updated = (object) [
                'id' => $entry->id,
                'rating' => $newgrade,
                'entrycomment' => $newfeedback,
                'teacher' => $USER->id,
                'timemarked' => $timenow,
                'mailed' => 0,
            ];

            $transaction = $DB->start_delegated_transaction();

            $DB->update_record('journal_entries', $updated);
            journal_update_grades($journal, $entry->userid);

            // Teaching-level event.
            $event = \mod_journal\event\feedback_updated::create([
                'objectid' => $journal->id,
                'context' => $context,
            ]);
            $event->add_record_snapshot('course_modules', $cm);
            $event->add_record_snapshot('course', $course);
            $event->add_record_snapshot('journal', $journal);
            $event->trigger();

            $transaction->allow_commit();
        }

        return [
            'status' => 'ok',
            'changed' => $changed ? 1 : 0,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'ok'),
            'changed' => new external_value(PARAM_INT, '1 if something changed, else 0'),
        ]);
    }
}
