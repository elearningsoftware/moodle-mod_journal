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
 * The report page for the mod_journal plugin
 *
 * @package mod_journal
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);   // Course module.
$sortby = optional_param('sortby', 'dateasc', PARAM_ALPHA);
$selecteduser = optional_param('selecteduser', 0, PARAM_INT);

$valid_sort_options = [
    'dateasc',
    'datedesc',
    'firstnameasc',
    'firstnamedesc',
    'lastnameasc',
    'lastnamedesc',
];
if (!in_array($sortby, $valid_sort_options)) {
    $sortby = 'dateasc';
}

$cm = get_coursemodule_from_id('journal', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$journal = $DB->get_record('journal', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/journal:manageentries', $context);

$PAGE->set_url('/mod/journal/report.php', ['id' => $id]);
$PAGE->navbar->add(get_string('entries', 'journal'));
$PAGE->set_title(get_string('modulenameplural', 'journal'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->js_call_amd('mod_journal/report', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('entries', 'journal'));

// Retrieve entries.
$entries = $DB->get_records('journal_entries', ['journal' => $journal->id]) ?: [];
$entrybyuser = [];
$entrybyentry = [];
foreach ($entries as $entry) {
    $entrybyuser[$entry->userid] = $entry;
    $entrybyentry[$entry->id] = $entry;
}

$userswithentries = array_map(function($entry) {
    return $entry->userid;
}, $entries);

// Group mode.
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);

$users = get_users_by_capability($context, 'mod/journal:addentries', '', '', '', '', $currentgroup, '', false, true);

$useroptions = [];
foreach ($users as $user) {
    if (in_array($user->id, $userswithentries)) {
        $useroptions[$user->id] = fullname($user);
    }
}

echo '<div class="d-flex justify-content-between align-items-center">';
echo '<form method="get" action="report.php" class="d-flex align-items-center">';
echo '<input type="hidden" name="id" value="'.$id.'">';
echo html_writer::select($useroptions, 'selecteduser', $selecteduser, ['' => get_string('allusers', 'search')], ['class' => 'mr-2']);
echo '<input type="submit" value="'.get_string('filter').'" class="btn btn-secondary mr-2">';
echo '</form>';
// Render group activity menu.
groups_print_activity_menu($cm, $PAGE->url);

// Sorting dropdown.
$options = [
    'dateasc' => get_string('dateasc', 'journal'),
    'datedesc' => get_string('datedesc', 'journal'),
    'firstnameasc' => get_string('firstnameasc', 'journal'),
    'firstnamedesc' => get_string('firstnamedesc', 'journal'),
    'lastnameasc' => get_string('lastnameasc', 'journal'),
    'lastnamedesc' => get_string('lastnamedesc', 'journal'),
];
$select = new single_select(
    new moodle_url($PAGE->url),
    'sortby',
    $options,
    $sortby,
    null
);
$select->set_label(get_string('sortby'));
echo html_writer::div($OUTPUT->render($select), 'divwrapper sortbyselect');
echo '</div>';

// Process incoming data if there is any.
if ($data = data_submitted()) {
    confirm_sesskey();
    $feedback = [];
    $data = (array) $data;

    // Extract ratings and comments.
    foreach ($data as $key => $val) {
        if (strpos($key, 'r') === 0 || strpos($key, 'c') === 0) {
            $type = substr($key, 0, 1);
            $num = substr($key, 1);
            $feedback[$num][$type] = (strpos($key, 'r') === 0 && $val === '') ? -1 : $val;
        }
    }

    $timenow = time();
    $count = 0;
    foreach ($feedback as $num => $vals) {
        $entry = $entrybyentry[$num];
        $ratingchanged = false;

        $studentrating = clean_param($vals['r'], PARAM_INT);
        $studentcomment = clean_text($vals['c']['text'], FORMAT_HTML);
        $studentcomment = file_save_draft_area_files($vals['c']['itemid'], $context->id, 'mod_journal', 'feedback', $num, [], $studentcomment);

        if ($studentrating != $entry->rating || $studentcomment != $entry->entrycomment) {
            $ratingchanged = $studentrating != $entry->rating;

            $newentry = (object) [
                'id' => $num,
                'rating' => $studentrating,
                'entrycomment' => $studentcomment,
                'teacher' => $USER->id,
                'timemarked' => $timenow,
                'mailed' => 0,
            ];
            if (!$DB->update_record('journal_entries', $newentry)) {
                echo $OUTPUT->notification(get_string('failedupdate', 'journal', $entry->userid), \core\output\notification::NOTIFY_ERROR);
            } else {
                $count++;
                $entrybyuser[$entry->userid]->rating = $studentrating;
                $entrybyuser[$entry->userid]->entrycomment = $studentcomment;
                $entrybyuser[$entry->userid]->teacher = $USER->id;
                $entrybyuser[$entry->userid]->timemarked = $timenow;

                $journal = $DB->get_record('journal', ['id' => $entrybyuser[$entry->userid]->journal]);
                $journal->cmidnumber = $cm->idnumber;
                journal_update_grades($journal, $entry->userid);
            }
        }
    }

    // Trigger feedback updated event.
    $event = \mod_journal\event\feedback_updated::create([
        'objectid' => $journal->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('journal', $journal);
    $event->trigger();

    echo $OUTPUT->notification(get_string('feedbackupdated', 'journal', $count), \core\output\notification::NOTIFY_SUCCESS);
} else {
    // Trigger entries viewed event.
    $event = \mod_journal\event\entries_viewed::create([
        'objectid' => $journal->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('journal', $journal);
    $event->trigger();
}
// Determine the group context.
if ($currentgroup) {
    $groups = $currentgroup;
} else {
    $groups = '';
}
$users = get_users_by_capability($context, 'mod/journal:addentries', '', '', '', '', $groups);

if (!$users) {
    echo $OUTPUT->notification(get_string('nousersyet', 'journal'), \core\output\notification::NOTIFY_INFO);
} else {
    $grades = make_grades_menu($journal->grade);
    if (!$teachers = get_users_by_capability($context, 'mod/journal:manageentries')) {
        throw new \moodle_exception(get_string('noentriesmanagers', 'journal'));
    }

    // Start the form.
    echo html_writer::start_tag('form', [
        'action' => $PAGE->url,
        'method' => 'post',
    ]);

    if ($usersdone = journal_get_users_done($journal, $currentgroup)) {
        mod_journal_sort_users($usersdone, $sortby, $entrybyuser);
        echo html_writer::tag('h3', get_string('userswhocompletedthejournal', 'journal'), ['class' => 'journalheader']);
        foreach ($usersdone as $user) {
            if ($selecteduser == 0 || $selecteduser == $user->id) {
                journal_print_user_entry($course, $user, $entrybyuser[$user->id], $teachers, $grades, $cm->id);
            }
            unset($users[$user->id]);
        }
    }

    if ($users) {
        mod_journal_sort_users($users, $sortby, $entrybyuser);
        echo html_writer::tag('h3', get_string('userswhodidnotcompletedthejournal', 'journal'), ['class' => 'journalheader']);
        foreach ($users as $user) {
            if ($selecteduser == 0 || $selecteduser == $user->id) {
                journal_print_user_entry($course, $user, null, $teachers, $grades, $cm->id);
            }
        }
    }

    // Add hidden input fields.
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'id',
        'value' => $cm->id,
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey(),
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sortby',
        'value' => $sortby,
    ]);

    // Add the submit button inside a paragraph with class.
    echo html_writer::tag(
        'p',
        html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('saveallfeedback', 'journal'),
            'class' => 'btn btn-secondary m-t-1',
        ]),
        ['class' => 'feedbacksave']
    );

    // Close the form.
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
