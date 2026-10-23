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
 * Edit page for mod_journal
 *
 * @package mod_journal
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/journal/lib.php');
require_once($CFG->dirroot . '/mod/journal/edit_form.php');

$id = required_param('id', PARAM_INT);    // Course Module ID.

if (!$cm = get_coursemodule_from_id('journal', $id)) {
    throw new \moodle_exception('incorrectcmid', 'journal');
}

if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
    throw new \moodle_exception('incorrectcourseid', 'journal');
}

$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/journal:addentries', $context);

if (!$journal = $DB->get_record('journal', ['id' => $cm->instance])) {
    throw new \moodle_exception('incorrectjournalid', 'journal');
}

if (!journal_is_open($journal, $course, $cm)) {
    redirect(
        new moodle_url('/mod/journal/view.php', ['id' => $cm->id]),
        get_string('journalclosed', 'journal'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Header.
$PAGE->set_url('/mod/journal/edit.php', ['id' => $id]);
$PAGE->navbar->add(get_string('edit'));
$PAGE->set_title(format_string($journal->name));
$PAGE->set_heading($course->fullname);

$PAGE->set_activity_record($journal);

$data = new stdClass();

$entry = $DB->get_record('journal_entries', ['userid' => $USER->id, 'journal' => $journal->id]);
if ($entry) {
    $data->entryid = $entry->id;
    $data->text = $entry->text;
    $data->textformat = $entry->format;
} else {
    $data->entryid = null;
    $data->text = '';
    $data->textformat = FORMAT_HTML;
}

$data->id = $cm->id;

$manager = \mod_journal\manager::create_from_coursemodule($cm);
$editoroptions = $manager->get_entry_editor_options();

$data = file_prepare_standard_editor($data, 'text', $editoroptions, $context, 'mod_journal', 'entry', $data->entryid);

$form = new mod_journal_entry_form(null, ['entryid' => $data->entryid, 'editoroptions' => $editoroptions]);
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/journal/view.php?id=' . $cm->id);
} else if ($fromform = $form->get_data()) {
    // If data submitted, then process and store.

    // Prevent CSFR.
    confirm_sesskey();

    $manager->save_entry(
        $fromform->text_editor['text'],
        $fromform->text_editor['format'],
        $fromform->text_editor['itemid']
    );

    redirect(new moodle_url('/mod/journal/view.php?id=' . $cm->id));
    die;
}


echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($journal->name));

$form->display();
echo $OUTPUT->footer();
