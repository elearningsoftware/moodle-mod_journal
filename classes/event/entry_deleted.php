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
 * The mod_journal entry deleted event.
 *
 * @package     mod_journal
 * @copyright   2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_journal\event;

/**
 * The mod_journal entry deleted class.
 *
 * @package    mod_journal
 * @since      Moodle 4.0
 * @copyright  2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_deleted extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'journal_entries';
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('evententrydeleted', 'mod_journal');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has deleted the entry with id '$this->objectid' of the user with id " .
            "'$this->relateduserid' for the journal " .
            "activity with the course module id '$this->contextinstanceid'";
    }

    /**
     * Returns relevant URL.
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/journal/report.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Validates the data of the event.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }

    /**
     * Returns how to map the entry when restoring logs.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'journal_entries', 'restore' => 'journal_entry'];
    }
}
