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
 * mod_journal view page
 *
 * @package    mod_journal
 * @copyright  2014 David Monllao <david.monllao@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.

$cm = get_coursemodule_from_id('journal', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$journal = $DB->get_record('journal', ['id' => $cm->instance], '*', MUST_EXIST);

if (!$cw = $DB->get_record('course_sections', ['id' => $cm->section])) {
    throw new moodle_exception(get_string('incorrectcoursesectionid', 'journal'));
}

$context = context_module::instance($cm->id);
require_login($course, true, $cm);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$entriesmanager = has_capability('mod/journal:manageentries', $context);
$canadd = has_capability('mod/journal:addentries', $context);

if (!$entriesmanager && !$canadd) {
    throw new moodle_exception(get_string('accessdenied', 'journal'));
}

$journalname = format_string($journal->name, true, ['context' => $context]);

$PAGE->set_url('/mod/journal/view.php', ['id' => $id]);
$PAGE->set_title($journalname);
$PAGE->set_heading($course->fullname);
$PAGE->force_settings_menu(); // Ensure settings menu is displayed.

// Display the page header.
echo $OUTPUT->header();

if ($CFG->branch < 400) {
    echo $OUTPUT->heading($journalname);
}

// Handle groups and group restrictions.
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
$allowedgroups = groups_get_activity_allowed_groups($cm);

groups_print_activity_menu($cm, $PAGE->url);

if ($entriesmanager) {
    if ($currentgroup === 0 && $groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
        $currentgroup = null;
    }
    if (!$currentgroup || array_key_exists($currentgroup, $allowedgroups)) {
        $entrycount = journal_count_entries($journal, $currentgroup);
        $managerlink = new moodle_url('/mod/journal/report.php', ['id' => $cm->id]);
        echo html_writer::div(
            html_writer::link($managerlink, get_string('viewallentries', 'journal', $entrycount)),
            'reportlink'
        );
    }
}

// Determine time constraints for journal editing.
$timenow = time();
if ($course->format == 'weeks' && $journal->days) {
    $timestart = $course->startdate + (($cm->section - 1) * 604800);
    $timefinish = $timestart + (3600 * 24 * $journal->days);
} else {
    $timestart = $timenow - 1;
    $timefinish = $timenow + 1;
    $journal->days = 0;
}

// Display journal entry form or message.
if ($timenow > $timestart) {
    echo $OUTPUT->box_start();

    // Render "Add/Edit Entry" button if within time constraints.
    if ($timenow < $timefinish && $canadd) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/journal/edit.php', ['id' => $cm->id]),
            get_string('startoredit', 'journal'),
            'get',
            ['class' => 'singlebutton journalstart mb-3', 'type' => 'primary']
        );
    }

    // Display existing journal entry.
    $entry = $DB->get_record('journal_entries', ['userid' => $USER->id, 'journal' => $journal->id]);
    if ($entry) {
        echo '<div>';
        if (empty($entry->text)) {
            echo $OUTPUT->notification(get_string('blankentry', 'journal'), \core\output\notification::NOTIFY_INFO);
        } else {
            echo journal_format_entry_text($entry, $course, $cm);
        }
        echo '</div>';
    } else {
        echo $OUTPUT->notification(get_string('notstarted', 'journal'), \core\output\notification::NOTIFY_WARNING);
    }

    echo $OUTPUT->box_end();

    // Display entry information and feedback.
    if ($timenow < $timefinish) {
        if (!empty($entry->modified)) {
            echo html_writer::div(
                '<strong>' . get_string('lastedited') . ':</strong> ' . userdate($entry->modified) . ' (' .
                get_string('numwords', '', count_words($entry->text)) . ')',
                'lastedit'
            );
        }
        if (!empty($entry->modified) && !empty($entry->timemarked) && $entry->modified > $entry->timemarked) {
            echo $OUTPUT->notification(get_string('needsregrade', 'journal'), \core\output\notification::NOTIFY_WARNING);
        }
        if (!empty($journal->days)) {
            echo html_writer::div(
                '<strong>' . get_string('editingends', 'journal') . ':</strong> ' . userdate($timefinish),
                'editend'
            );
        }
    } else {
        echo $OUTPUT->notification(
            '<strong>' . get_string('editingended', 'journal') . ':</strong> ' . userdate($timefinish),
            \core\output\notification::NOTIFY_WARNING
        );
    }

    // Feedback.
    if (!(empty($entry->entrycomment) || (!empty($entry->rating) && !$entry->rating))) {
        $grades = make_grades_menu($journal->grade);
        echo $OUTPUT->heading(get_string('feedback'));
        journal_print_feedback($course, $entry, $grades);
    }
} else {
    echo $OUTPUT->notification(get_string('notopenuntil', 'journal')
        . ': ' . userdate($timestart), \core\output\notification::NOTIFY_WARNING);
}

// Trigger the module viewed event.
$event = \mod_journal\event\course_module_viewed::create([
    'objectid' => $journal->id,
    'context' => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('journal', $journal);
$event->trigger();

// Display footer.
echo $OUTPUT->footer();
