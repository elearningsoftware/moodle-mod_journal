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
 * mod_journal backup moodle 2 structure
 *
 * @package    mod_journal
 * @copyright  2014 David Monllao <david.monllao@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The backup_journal_activity_structure_step class.
 *
 * @package    mod_journal
 * @copyright  2022 Elearning Software SRL http://elearningsoftware.ro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_journal_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure
     *
     * @return void
     */
    protected function define_structure() {

        $journal = new backup_nested_element('journal', ['id'], [
            'name', 'intro', 'introformat', 'days', 'grade', 'timemodified', 'completion_create_entry',]);

        $entries = new backup_nested_element('entries');

        $entry = new backup_nested_element('entry', ['id'], [
            'userid', 'modified', 'text', 'format', 'rating',
            'entrycomment', 'teacher', 'timemarked', 'mailed', ]);

        // Journal -> entries -> entry.
        $journal->add_child($entries);
        $entries->add_child($entry);

        // Sources.
        $journal->set_source_table('journal', ['id' => backup::VAR_ACTIVITYID]);

        if ($this->get_setting_value('userinfo')) {
            $entry->set_source_table('journal_entries', ['journal' => backup::VAR_PARENTID]);
        }

        // Define id annotations.
        $entry->annotate_ids('user', 'userid');
        $entry->annotate_ids('user', 'teacher');

        // Define file annotations.
        $journal->annotate_files('mod_journal', 'intro', null); // This file areas haven't itemid.
        $entry->annotate_files('mod_journal_entries', 'text', null); // This file areas haven't itemid.
        $entry->annotate_files('mod_journal_entries', 'entrycomment', null); // This file areas haven't itemid.

        return $this->prepare_activity_structure($journal);
    }
}
