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

use core_completion\activity_custom_completion;

/**
 * Custom completion rules for mod_journal
 *
 * @package     mod_journal
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Get the completion state of the custom completion rules
     * @param string $rule completion rule
     * @return int completion state
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $createentry = intval($DB->get_field('journal', 'completion_create_entry', ['id' => $this->cm->instance], MUST_EXIST));

        if (!empty($createentry)) {
            $entry = $DB->record_exists('journal_entries', ['journal' => $this->cm->instance, 'userid' => $this->userid]);
            return $entry ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Get all custom completion rules.
     *
     * @return array Array of custom completion rules.
     */
    public static function get_defined_custom_rules(): array {
        return ['completion_create_entry'];
    }

    /**
     * Get custom rule descriptions.
     *
     * @return array Array of custom rule descriptions.
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completion_create_entry' => get_string('completiondetail:completion_create_entry', 'journal'),
        ];
    }

    /**
     * Get the sort order of the custom completion rules.
     *
     * @return array Array of custom completion rules.
     */
    public function get_sort_order(): array {
        return ['completion_create_entry', 'completionusegrade', 'completionpassgrade', 'completionview'];
    }
}
