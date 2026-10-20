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
 * Upgrade script for mod_journal
 *
 * @package mod_journal
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/journal/lib.php');

/**
 * Upgrade steps for mod_journal
 *
 * @param integer $oldversion Old plugin version
 * @return bool True if succesfull, false otherwise
 */
function xmldb_journal_upgrade($oldversion = 0) {
    global $DB;

    $dbman = $DB->get_manager();

    // No DB changes since 1.9.0.

    // Add journal instances to the gradebook.
    if ($oldversion < 2010120300) {
        journal_update_grades();

        upgrade_mod_savepoint(true, 2010120300, 'journal');
    }

    // Change assessed field for grade.
    if ($oldversion < 2011040600) {
        // Rename field assessed on table journal to grade.
        $table = new xmldb_table('journal');
        $field = new xmldb_field('assessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'days');

        // Launch rename field grade.
        $dbman->rename_field($table, $field, 'grade');

        // Journal savepoint reached.
        upgrade_mod_savepoint(true, 2011040600, 'journal');
    }

    if ($oldversion < 2012032001) {
        // Changing the default of field rating on table journal_entries to drop it.
        $table = new xmldb_table('journal_entries');
        $field = new xmldb_field('rating', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'format');

        // Launch change of default for field rating.
        $dbman->change_field_default($table, $field);

        // Updating the non-marked entries with rating = NULL.
        $entries = $DB->get_records('journal_entries', ['timemarked' => 0]);
        if ($entries) {
            foreach ($entries as $entry) {
                $entry->rating = null;
                $DB->update_record('journal_entries', $entry);
            }
        }

        // Journal savepoint reached.
        upgrade_mod_savepoint(true, 2012032001, 'journal');
    }

    if ($oldversion < 2022041100) {
        // Changing the default of field rating on table
        // journal_entries to fix
        // https://github.com/elearningsoftware/moodle-mod_journal/issues/61.
        $table = new xmldb_table('journal_entries');
        $field = new xmldb_field('rating', XMLDB_TYPE_INTEGER, '10', null, null, null, -1, 'format');

        // Launch change of default for field rating.
        $dbman->change_field_default($table, $field);

        // Updating the non-marked entries with rating = -1.
        $DB->set_field_select('journal_entries', 'rating', '-1', 'timemarked = 0');

        // Journal savepoint reached.
        upgrade_mod_savepoint(true, 2022041100, 'journal');
    }

    if ($oldversion < 2025120700) {
        // Define field completion_create_entry to be added to journal.
        $table = new xmldb_table('journal');
        $field = new xmldb_field(
            'completion_create_entry',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'timemodified'
        );

        // Conditionally launch add field completion_create_entry.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add notifyteachers field.
        $field = new xmldb_field(
            'notifyteachers',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'completion_create_entry'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add notifystudents field.
        $field = new xmldb_field(
            'notifystudents',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'notifyteachers'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Journal savepoint reached.
        upgrade_mod_savepoint(true, 2025120700, 'journal');
    }

    if ($oldversion < 2026102001) {
        // Remove duplicate entries of a user in a journal, keeping the most recently modified one.
        $fs = get_file_storage();
        $duplicates = $DB->get_recordset_sql('SELECT journal, userid
                                                FROM {journal_entries}
                                            GROUP BY journal, userid
                                              HAVING COUNT(1) > 1');
        foreach ($duplicates as $duplicate) {
            $entries = $DB->get_records(
                'journal_entries',
                ['journal' => $duplicate->journal, 'userid' => $duplicate->userid],
                'modified DESC, id DESC',
                'id'
            );
            array_shift($entries);
            $cm = get_coursemodule_from_instance('journal', $duplicate->journal);
            foreach ($entries as $entry) {
                if ($cm) {
                    $context = context_module::instance($cm->id);
                    $fs->delete_area_files($context->id, 'mod_journal', 'entry', $entry->id);
                    $fs->delete_area_files($context->id, 'mod_journal', 'feedback', $entry->id);
                }
                $DB->delete_records('journal_entries', ['id' => $entry->id]);
            }
        }
        $duplicates->close();

        // Define index journaluser (unique) to be added to journal_entries.
        $table = new xmldb_table('journal_entries');
        $index = new xmldb_index('journaluser', XMLDB_INDEX_UNIQUE, ['journal', 'userid']);

        // Conditionally launch add index journaluser.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Journal savepoint reached.
        upgrade_mod_savepoint(true, 2026102001, 'journal');
    }

    return true;
}
