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
 * This page lists all the instances of journal in a particular course
 *
 * @package mod_journal
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

/** Include required files */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/journal/lib.php');

$id = required_param('id', PARAM_INT);   // course

$PAGE->set_url('/mod/journal/index.php', array('id'=>$id));

if (!$course = $DB->get_record("course", array("id" => $id))) {
    print_error('invalidcourseid');
}

require_login($course);
$PAGE->set_pagelayout('incourse');

// Trigger instances list viewed event.
$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_journal\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

/// Get all required strings

$strjournals = get_string("modulenameplural", "journal");
$strjournal  = get_string("modulename", "journal");


/// Print the header
$PAGE->navbar->add($strjournals);
$PAGE->set_title("$course->shortname: $strjournals");
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($strjournals, 2);

/// Get all the appropriate data

if (! $journals = get_all_instances_in_course("journal", $course)) {
    notice(get_string('thereareno', 'moodle', $strjournals), "../../course/view.php?id=$course->id");
    die;
}

$usesections = course_format_uses_sections($course->format);

/// Print the list of instances (your module will probably extend this)

$timenow = time();
$strname  = get_string("name");
$strgrade  = get_string("grade");
$strdeadline  = get_string("deadline", "journal");
$strnodeadline = get_string("nodeadline", "journal");
$table = new html_table();

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = array ($strsectionname, $strname, $strgrade, $strdeadline);
    $table->align = array ("center", "left", "center", "center");
} else {
    $table->head  = array ($strname, $strgrade, $strdeadline);
    $table->align = array ("left", "center", "center");
}

foreach ($journals as $journal) {
    if (!$journal->visible) {
        //Show dimmed if the mod is hidden
        $link = "<a class=\"dimmed\" href=\"view.php?id=$journal->coursemodule\">".format_string($journal->name,true)."</a>";
    } else {
        //Show normal if the mod is visible
        $link = "<a href=\"view.php?id=$journal->coursemodule\">".format_string($journal->name,true)."</a>";
    }
    $cm = get_coursemodule_from_instance('journal', $journal->id);
    $context = context_module::instance($cm->id);

    if ($journal->days == 0) {
        $due = $strnodeadline;
    } else if ($journal->days > $timenow) {
        $due = userdate($journal->days);
    } else {
        // $due = "<font color=\"red\">".userdate($journal->days)."</font>";
		$due = "<font color=\"red\">".($journal->days)."</font>";
    }

    if ($usesections) {
        if (has_capability('mod/journal:manageentries', $context)) {
            $grade_value = $journal->grade;
        } else {
            // it's a student, show their grade
            $grade_value = 0;
            if ($return = journal_get_user_grades($journal, $USER->id)) {
                $grade_value = $return[$USER->id]->rawgrade;
            }
        }
        $table->data[] = array (get_section_name($course, $journal->section), $link, $grade_value, $due);
    } else {
        $table->data[] = array ($link, $journal->grade, $due);
    }
}
echo html_writer::table($table);
echo $OUTPUT->footer();
