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
 * @package     mod_journal
 * @copyright   1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);   // Course module.
$sortby = optional_param('sortby', 'dateasc', PARAM_ALPHA);
$selecteduser = optional_param('selecteduser', 0, PARAM_INT);
$show = optional_param('show', 'all', PARAM_ALPHA);
if ($show !== 'needsgrading') {
    $show = 'all';
}
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);

$perpageoptions = [10 => 10, 20 => 20, 50 => 50, 100 => 100, 0 => get_string('all')];
if (!array_key_exists($perpage, $perpageoptions)) {
    $perpage = 20;
}

$validsortoptions = [
    'dateasc',
    'datedesc',
    'firstnameasc',
    'firstnamedesc',
    'lastnameasc',
    'lastnamedesc',
];
if (!in_array($sortby, $validsortoptions)) {
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

$PAGE->set_activity_record($journal);

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

// Map users with entries for the filter.
$userswithentries = array_map(function ($entry) {
    return $entry->userid;
}, $entries);

// Group mode.
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);

// In separate groups mode, a grader who is in no group and cannot access all groups must not see any entries.
if ($groupmode == SEPARATEGROUPS && !$currentgroup && !has_capability('moodle/site:accessallgroups', $context)) {
    echo $OUTPUT->notification(get_string('notingroup'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

// Fetch users who can ADD entries (Students, but also Teachers/Admins).
$groups = $currentgroup ? $currentgroup : '';
$users = get_users_by_capability($context, 'mod/journal:addentries', '', '', '', '', $groups);

// Fetch users who can MANAGE entries (Teachers, Managers, Admins).
$teachers = get_users_by_capability($context, 'mod/journal:manageentries');

// Filter out Teachers/Managers from the "Users" list.
// This ensures they don't appear in the "Users who have not completed the journal" list.
if ($users && $teachers) {
    foreach ($teachers as $teacherid => $teacher) {
        if (isset($users[$teacherid])) {
            unset($users[$teacherid]);
        }
    }
}

// Build filter options.
$useroptions = [];
if ($users) {
    foreach ($users as $user) {
        if (in_array($user->id, $userswithentries)) {
            $useroptions[$user->id] = fullname($user);
        }
    }
}

// Process incoming data if there is any.
if ($data = data_submitted()) {
    require_sesskey();
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

    $manager = \mod_journal\manager::create_from_coursemodule($cm);
    $count = 0;
    foreach ($feedback as $num => $vals) {
        if (!isset($entrybyentry[$num])) {
            continue;
        }
        $entry = $entrybyentry[$num];
        if (!groups_user_groups_visible($course, $entry->userid, $cm)) {
            continue;
        }

        // Locked and overridden grades have no feedback editor, their feedback stays unchanged.
        if (isset($vals['c']) && is_array($vals['c'])) {
            $feedbacktext = (string) ($vals['c']['text'] ?? '');
            $draftitemid = clean_param($vals['c']['itemid'] ?? 0, PARAM_INT);
        } else {
            $feedbacktext = (string) $entry->entrycomment;
            $draftitemid = 0;
        }

        try {
            $changed = $manager->save_feedback(
                $entry,
                clean_param($vals['r'] ?? $entry->rating, PARAM_INT),
                $feedbacktext,
                $draftitemid
            );
        } catch (invalid_parameter_exception $e) {
            echo $OUTPUT->notification(
                get_string('failedupdate', 'journal', $entry->userid),
                \core\output\notification::NOTIFY_ERROR
            );
            continue;
        }
        if ($changed) {
            $count++;
        }
    }

    if ($count > 0) {
        echo $OUTPUT->notification(get_string('feedbackupdated', 'journal', $count), \core\output\notification::NOTIFY_SUCCESS);
    }
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

$usersdone = journal_get_users_done($journal, $currentgroup) ?: [];
mod_journal_sort_users($usersdone, $sortby, $entrybyuser);
$needsgradingcount = 0;
foreach ($usersdone as $user) {
    if (journal_entry_needs_grading($entrybyuser[$user->id] ?? null)) {
        $needsgradingcount++;
    }
}

// The users whose entries are shown, in order, to grade them one by one. The selected user stays in the list once graded.
$getgradeableids = function (int $selecteduser) use ($usersdone, $entrybyuser, $show): array {
    $ids = [];
    foreach ($usersdone as $user) {
        if ($show === 'all' || $user->id == $selecteduser || journal_entry_needs_grading($entrybyuser[$user->id])) {
            $ids[] = (int) $user->id;
        }
    }
    return $ids;
};
$gradeableids = $getgradeableids($selecteduser);
$position = array_search($selecteduser, $gradeableids);

// Move on to the next entry once the feedback of the selected one is saved.
if ($position !== false && isset($gradeableids[$position + 1]) && optional_param('savenext', '', PARAM_RAW) !== '') {
    $selecteduser = $gradeableids[$position + 1];
    $gradeableids = $getgradeableids($selecteduser);
    $position = array_search($selecteduser, $gradeableids);
}

if (!$users && !$usersdone) {
    echo $OUTPUT->notification(get_string('nousersyet', 'journal'), \core\output\notification::NOTIFY_INFO);
} else {
    $baseurl = new moodle_url('/mod/journal/report.php', [
        'id' => $id,
        'selecteduser' => $selecteduser,
        'sortby' => $sortby,
        'show' => $show,
        'perpage' => $perpage,
    ]);

    // Toolbar Area: Filter, Group, Sort.
    echo html_writer::start_div('d-flex flex-wrap justify-content-between align-items-center mb-3');

    // 1. User Filter Form.
    $filterform = html_writer::start_tag('form', [
        'method' => 'get',
        'action' => 'report.php',
        'class' => 'd-flex align-items-center me-3', // Class me-3 for spacing (Bootstrap 5).
    ]);
    $filterform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
    $filterform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sortby', 'value' => $sortby]);
    $filterform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'show', 'value' => $show]);
    $filterform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'perpage', 'value' => $perpage]);
    $filterform .= html_writer::select(
        $useroptions,
        'selecteduser',
        $selecteduser,
        ['' => get_string('allusers', 'search')],
        ['class' => 'form-select me-2'] // Classes form-select and me-2 (Bootstrap 5).
    );
    $filterform .= html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('filter'),
        'class' => 'btn btn-secondary',
    ]);
    $filterform .= html_writer::end_tag('form');
    echo $filterform;

    // 2. Group Activity Menu.
    echo html_writer::div('', 'me-3'); // Spacer.
    groups_print_activity_menu($cm, $PAGE->url);

    // 3. Sorting Dropdown.
    $options = [
        'dateasc' => get_string('dateasc', 'journal'),
        'datedesc' => get_string('datedesc', 'journal'),
        'firstnameasc' => get_string('firstnameasc', 'journal'),
        'firstnamedesc' => get_string('firstnamedesc', 'journal'),
        'lastnameasc' => get_string('lastnameasc', 'journal'),
        'lastnamedesc' => get_string('lastnamedesc', 'journal'),
    ];
    $select = new single_select(
        new moodle_url($baseurl, ['sortby' => null]),
        'sortby',
        $options,
        $sortby,
        null,
    );
    $select->set_label(get_string('sortby'), ['class' => 'me-1']);
    echo html_writer::div($OUTPUT->render($select), 'divwrapper sortbyselect');

    // 4. Entries to show.
    $select = new single_select(
        new moodle_url($baseurl, ['show' => null]),
        'show',
        [
            'all' => get_string('all'),
            'needsgrading' => get_string('needsgradingcount', 'journal', $needsgradingcount),
        ],
        $show,
        null,
    );
    $select->set_label(get_string('show'), ['class' => 'me-1']);
    echo html_writer::div($OUTPUT->render($select), 'divwrapper showselect');

    // 5. Entries per page.
    $select = new single_select(new moodle_url($baseurl, ['perpage' => null]), 'perpage', $perpageoptions, $perpage, null);
    $select->set_label(get_string('perpage'), ['class' => 'me-1']);
    echo html_writer::div($OUTPUT->render($select), 'divwrapper perpageselect');

    echo html_writer::end_div(); // End Toolbar.

    // Navigation between the entries, to grade them one by one.
    if ($position !== false) {
        $navigation = [];
        if ($position > 0) {
            $navigation[] = html_writer::link(
                new moodle_url($baseurl, ['selecteduser' => $gradeableids[$position - 1]]),
                get_string('previous'),
                ['class' => 'btn btn-secondary me-2 mr-2 previousentry']
            );
        }
        $navigation[] = html_writer::span(
            get_string('entryxofy', 'journal', ['index' => $position + 1, 'count' => count($gradeableids)]),
            'me-2 mr-2 entryposition'
        );
        if (isset($gradeableids[$position + 1])) {
            $navigation[] = html_writer::link(
                new moodle_url($baseurl, ['selecteduser' => $gradeableids[$position + 1]]),
                get_string('next'),
                ['class' => 'btn btn-secondary me-2 mr-2 nextentry']
            );
        }
        $navigation[] = html_writer::link(
            new moodle_url($baseurl, ['selecteduser' => 0]),
            get_string('allentries', 'journal'),
            ['class' => 'btn btn-link allentries']
        );
        echo html_writer::div(implode('', $navigation), 'd-flex align-items-center mb-3 entrynavigation');
    } else if ($selecteduser == 0 && $gradeableids) {
        echo html_writer::div(html_writer::link(
            new moodle_url($baseurl, ['selecteduser' => $gradeableids[0]]),
            get_string('gradeonebyone', 'journal'),
            ['class' => 'btn btn-secondary gradeonebyone']
        ), 'mb-3');
    }

    $grades = make_grades_menu($journal->grade);

    // Start the form.
    echo html_writer::start_tag('form', [
        'action' => $PAGE->url,
        'method' => 'post',
    ]);

    if ($show === 'needsgrading' && !$needsgradingcount) {
        echo $OUTPUT->notification(get_string('noentriesneedgrading', 'journal'), \core\output\notification::NOTIFY_INFO);
    }

    // Users with an entry come first, then the users without an entry, who have nothing to grade.
    $rows = [];
    foreach ($usersdone as $user) {
        if ($selecteduser == 0 ? in_array($user->id, $gradeableids) : $selecteduser == $user->id) {
            $rows[] = [$user, $entrybyuser[$user->id]];
        }
        // Remove user from the "Not Completed" list if they are in the "Done" list.
        unset($users[$user->id]);
    }
    if ($users && $show === 'all') {
        mod_journal_sort_users($users, $sortby, $entrybyuser);
        foreach ($users as $user) {
            if ($selecteduser == 0 || $selecteduser == $user->id) {
                $rows[] = [$user, null];
            }
        }
    }

    if ($perpage) {
        $page = min(max($page, 0), max((int) ceil(count($rows) / $perpage) - 1, 0));
        $pagingbar = $OUTPUT->paging_bar(count($rows), $page, $perpage, $baseurl);
        $rows = array_slice($rows, $page * $perpage, $perpage);
    } else {
        $page = 0;
        $pagingbar = '';
    }

    echo $pagingbar;
    $heading = null;
    foreach ($rows as [$user, $entry]) {
        $rowheading = $entry ? 'userswhocompletedthejournal' : 'userswhodidnotcompletedthejournal';
        if ($rowheading !== $heading) {
            echo html_writer::tag('h3', get_string($rowheading, 'journal'), ['class' => 'journalheader']);
            $heading = $rowheading;
        }
        journal_print_user_entry($course, $user, $entry, $teachers, $grades, $cm->id);
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

    // Keep the user filter active when saving feedback.
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'selecteduser',
        'value' => $selecteduser,
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'show',
        'value' => $show,
    ]);

    // Stay on the same page when saving feedback.
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'page',
        'value' => $page,
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'perpage',
        'value' => $perpage,
    ]);

    // Add the submit button inside a paragraph with class.
    $savebuttons = html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('saveallfeedback', 'journal'),
        'class' => 'btn btn-secondary mt-1',
    ]);
    if ($position !== false && isset($gradeableids[$position + 1])) {
        $savebuttons .= ' ' . html_writer::empty_tag('input', [
            'type' => 'submit',
            'name' => 'savenext',
            'value' => get_string('savefeedbackandnext', 'journal'),
            'class' => 'btn btn-primary mt-1',
        ]);
    }
    echo html_writer::tag('p', $savebuttons, ['class' => 'feedbacksave']);

    // Close the form.
    echo html_writer::end_tag('form');

    echo $pagingbar;
}

echo $OUTPUT->footer();
