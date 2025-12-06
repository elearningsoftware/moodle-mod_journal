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
 * mod_journal data generator.
 *
 * @package    mod_journal
 * @category   test
 * @copyright  2014 David Monllao <david.monllao@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * mod_journal data generator class.
 *
 * @package    mod_journal
 * @category   test
 * @copyright  2014 David Monllao <david.monllao@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_journal_generator extends testing_module_generator {
    /**
     * @var int keep track of how many journals have been created.
     */
    protected $journalcount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->journalcount = 0;
        parent::reset();
    }

    /**
     * Create instance of mod_journal
     *
     * @param [type] $record Jounal record
     * @param ?array $options Options array
     * @return void
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        if (!isset($record->name)) {
            $record->name = 'Test journal name ' . $this->journalcount;
        }
        if (!isset($record->intro)) {
            $record->intro = 'Test journal name ' . $this->journalcount;
        }
        if (!isset($record->days)) {
            $record->days = 0;
        }
        if (!isset($record->grade)) {
            $record->grade = 100;
        }

        $this->journalcount++;

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Create a journal entry.
     *
     * @param object $record Journal entry record, needs at least the journal ID.
     * @return int The ID of the created journal entry
     */
    public function create_entry(object $record): int {
        global $DB, $USER;

        if (!isset($record->journal)) {
            throw new coding_exception('You must specify the journal ID in the record to create a journal entry.');
        }
        if (!isset($record->userid)) {
            $record->userid = $USER->id;
        }
        if (!isset($record->text)) {
            $record->text = 'The student\'s journal entry text.';
        }

        return $DB->insert_record('journal_entries', $record);
    }
}
