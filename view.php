<?php  // $Id: view.php,v 1.3 2011/04/06 15:20:30 davmon Exp $

require_once("../../config.php");
require_once("lib.php");


$id = required_param('id', PARAM_INT);    // Course Module ID

if (! $cm = get_coursemodule_from_id('journal', $id)) {
    print_error("Course Module ID was incorrect");
}

if (! $course = $DB->get_record("course", array('id' => $cm->course))) {
    print_error("Course is misconfigured");
}

$context = context_module::instance($cm->id);

require_login($course->id, true, $cm);

$entriesmanager = has_capability('mod/journal:manageentries', $context);
$canadd = has_capability('mod/journal:addentries', $context);

if (!$entriesmanager && !$canadd) {
    print_error('accessdenied', 'journal');
}

if (! $journal = $DB->get_record("journal", array("id" => $cm->instance))) {
    print_error("Course module is incorrect");
}

if (! $cw = $DB->get_record("course_sections", array("id" => $cm->section))) {
    print_error("Course module is incorrect");
}


// Header
$PAGE->set_url('/mod/journal/view.php', array('id'=>$id));
$PAGE->navbar->add(format_string($journal->name));
$PAGE->set_title(format_string($journal->name));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($journal->name));

/// Check to see if groups are being used here
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
groups_print_activity_menu($cm, $CFG->wwwroot . "/mod/journal/view.php?id=$cm->id");


if ($entriesmanager) {
    $entrycount = journal_count_entries($journal, $currentgroup);

    echo '<div class="reportlink"><a href="report.php?id='.$cm->id.'">'.
          get_string('viewallentries','journal', $entrycount).'</a></div>';

}

$journal->intro = trim($journal->intro);

if (!empty($journal->intro)) {

    $intro = format_module_intro('journal', $journal, $cm->id);
    echo $OUTPUT->box($intro, 'generalbox', 'intro');
}

echo '<br />';

$timenow = time();
if ($course->format == 'weeks' and $journal->days) {
    $timestart = $course->startdate + (($cw->section - 1) * 604800);
    if ($journal->days) {
        $timefinish = $timestart + (3600 * 24 * $journal->days);
    } else {
        $timefinish = $course->enddate;
    }
} else {  // Have no time limits on the journals

    $timestart = $timenow - 1;
    $timefinish = $timenow + 1;
    $journal->days = 0;
}
if ($timenow > $timestart) {

    echo $OUTPUT->box_start();

    // Edit button
    if ($timenow < $timefinish) {

        if ($canadd) {
            echo $OUTPUT->single_button('edit.php?id='.$cm->id, get_string('startoredit','journal'), 'get',
                array("class" => "singlebutton journalstart"));
        }
    }

    // Display entry
    if ($entry = $DB->get_record('journal_entries', array('userid' => $USER->id, 'journal' => $journal->id))) {
        if (empty($entry->text)) {
            echo '<p align="center"><b>'.get_string('blankentry','journal').'</b></p>';
        } else {
            echo format_text($entry->text, $entry->format);
        }
    } else {
        echo '<span class="warning">'.get_string('notstarted','journal').'</span>';
    }

    echo $OUTPUT->box_end();

    // Info
    if ($timenow < $timefinish) {
        if (!empty($entry->modified)) {
            echo '<div class="lastedit"><strong>'.get_string('lastedited').': </strong> ';
            echo userdate($entry->modified);
            echo ' ('.get_string('numwords', '', count_words($entry->text)).')';
            echo "</div>";
        }
		//Added three lines to mark entry as being dirty and needing regrade.
		if (!empty($entry->modified) AND (($entry->modified) > $entry->timemarked)) {
			echo "<div class=\"lastedit\">".get_string("needsregrade", "journal"). "</div>";
		} 

        if (!empty($journal->days)) {
            echo '<div class="editend"><strong>'.get_string('editingends', 'journal').': </strong> ';
            echo userdate($timefinish).'</div>';
        }

    } else {
        echo '<div class="editend"><strong>'.get_string('editingended', 'journal').': </strong> ';
        echo userdate($timefinish).'</div>';
    }

    // Feedback
    if (!empty($entry->entrycomment) or !empty($entry->rating)) {
        $grades = make_grades_menu($journal->grade);
        echo $OUTPUT->heading(get_string('feedback'));
        journal_print_feedback($course, $entry, $grades);
    }

} else {
    echo '<div class="warning">'.get_string('notopenuntil', 'journal').': ';
    echo userdate($timestart).'</div>';
}


// Trigger module viewed event.
$event = \mod_journal\event\course_module_viewed::create(array(
   'objectid' => $journal->id,
   'context' => $context
));
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('journal', $journal);
$event->trigger();

echo $OUTPUT->footer();
