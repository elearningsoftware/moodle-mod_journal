<?php // $Id: edit.php,v 1.2 2011/01/24 11:36:57 davmon Exp $

require_once("../../config.php");
require_once('./edit_form.php');

$id = required_param('id', PARAM_INT);    // Course Module ID

if (!$cm = get_coursemodule_from_id('journal', $id)) {
    print_error("Course Module ID was incorrect");
}

if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error("Course is misconfigured");
}

$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/journal:addentries', $context);

if (! $journal = $DB->get_record("journal", array("id" => $cm->instance))) {
    print_error("Course module is incorrect");
}

// Header
$PAGE->set_url('/mod/journal/edit.php', array('id' => $id));
$PAGE->navbar->add(get_string('edit'));
$PAGE->set_title(format_string($journal->name));
$PAGE->set_heading($course->fullname);

$data = new StdClass();

$entry = $DB->get_record("journal_entries", array("userid" => $USER->id, "journal" => $journal->id));
if ($entry) {
    $data->text["text"] = $entry->text;
}

$data->id = $cm->id;
$form = new mod_journal_entry_form(null, array('current' => $data));

if ($form->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/journal/view.php?id=' . $cm->id);
} else if ($fromform = $form->get_data()) {
    /// If data submitted, then process and store.

    // Prevent CSFR.
    confirm_sesskey();

    $timenow = time();

    // Common
    $newentry = new StdClass();
    $newentry->text = $fromform->text["text"];
    $newentry->format = $fromform->text["format"];
    $newentry->modified = $timenow;

    if ($entry) {
        $newentry->id = $entry->id;
        if (!$DB->update_record("journal_entries", $newentry)) {
            print_error("Could not update your journal");
        }
        $logaction = "update entry";

    } else {
        $newentry->userid = $USER->id;
        $newentry->journal = $journal->id;
        if (!$newentry->id = $DB->insert_record("journal_entries", $newentry)) {
            print_error("Could not insert a new journal entry");
        }
        $logaction = "add entry";
    }

    // Trigger module entry updated event.
    $event = \mod_journal\event\entry_updated::create(array(
        'objectid' => $journal->id,
        'context' => $context
    ));
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('journal', $journal);
    $event->trigger();

    redirect(new moodle_url('/mod/journal/view.php?id='.$cm->id));
    die;
}


echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($journal->name));

$intro = format_module_intro('journal', $journal, $cm->id);
echo $OUTPUT->box($intro);

/// Otherwise fill and print the form.
$form->display();

echo $OUTPUT->footer();
