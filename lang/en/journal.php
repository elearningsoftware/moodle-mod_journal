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
 * Language strings for mod_journal
 *
 * @package mod_journal
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

// Allow comments in this lang file.
// phpcs:disable moodle.Files.LangFilesOrdering.UnexpectedComment

$string['accessdenied'] = 'Access denied';
$string['alwaysopen'] = 'Always open';
$string['blankentry'] = 'Blank entry';
$string['changessaved'] = 'Changes saved';
$string['completiondetail:completion_create_entry'] = 'Create a journal entry';
$string['couldnotupdatejournal'] = 'Could not update your journal';
$string['countnotinsertjournalentry'] = 'Could not insert a new journal entry';
$string['crontask'] = 'Background processing for journal module';
$string['dateasc'] = 'Date Ascending';
$string['datedesc'] = 'Date Descending';
$string['daysavailable'] = 'Days available';
$string['deadline'] = 'Days Open';
$string['defaultgrade'] = 'Default entry grade';
$string['defaultgrade_help'] = 'The default maximum grade for new Journal entries.
* Enter **100** (or any positive number) to default to Point grading.
* Enter **0** to default to "No grade" (useful for formative activities).';
$string['editingended'] = 'Editing period has ended';
$string['editingends'] = 'Editing period ends';
$string['entries'] = 'Entries';
$string['entry'] = 'Entry';
$string['evententriesviewed'] = 'Journal entries viewed';
$string['evententrycreated'] = 'Journal entry created';
$string['evententryupdated'] = 'Journal entry updated';
$string['eventfeedbackupdated'] = 'Journal feedback updated';
$string['eventjournalcreated'] = 'Journal created';
$string['eventjournaldeleted'] = 'Journal deleted';
$string['eventjournalviewed'] = 'Journal viewed';
$string['failedupdate'] = 'Failed to update the journal feedback for {$a}';
$string['feedback'] = 'Feedback';
$string['feedbackupdated'] = 'Feedback updated for {$a} entries';
$string['feedbackupdatedforuser'] = 'Feedback updated for {$a}';
$string['firstnameasc'] = 'Firstname Ascending';
$string['firstnamedesc'] = 'Firstname Descending';
$string['grade'] = 'Grade';
$string['gradedby'] = 'Graded by';
$string['gradeingradebook'] = 'Current grade in gradebook';
$string['incorrectcmid'] = 'Course module ID is incorrect';
$string['incorrectcourseid'] = 'Course ID is incorrect';
$string['incorrectcoursesectionid'] = 'Selected course section is incorrect';
$string['incorrectjournalentry'] = 'Selected journal entry is incorrect';
$string['incorrectjournalid'] = 'Selected journal ID is incorrect';
$string['incorrectuserid'] = 'Selected user ID is incorrect';
$string['journal:addentries'] = 'Add journal entries';
$string['journal:addinstance'] = 'Add a new journal';
$string['journal:manageentries'] = 'Manage journal entries';

// Updated Plain Text Template.
$string['journalmail'] = 'Hi {$a->student},

{$a->teacher} has posted feedback on your journal entry for \'{$a->journal}\'.

Course: {$a->course_name}
Journal: {$a->journal}
Date: {$a->date}

You can view the feedback here:
    {$a->url}';
$string['journalmailhtml'] = '<p>Hi {$a->student},</p>
<p>{$a->teacher} has posted feedback on your journal entry for \'<strong>{$a->journal}</strong>\'.</p>
<p>
    <strong>Course:</strong> {$a->course_name}<br />
    <strong>Journal:</strong> {$a->journal}<br />
    <strong>Date:</strong> {$a->date}
</p>
<p>You can view the feedback <a href="{$a->url}">here</a>.</p>';
$string['journalname'] = 'Journal name';
$string['journalquestion'] = 'Journal question';
$string['lastedited'] = 'Last edited';
$string['lastnameasc'] = 'Lastname Ascending';
$string['lastnamedesc'] = 'Lastname Descending';
$string['mailbody'] = '{$a->username} has updated the journal entry for \'{$a->journalname}\'

You can view the entry here:
    {$a->url}';
$string['mailbodyhtml'] = '{$a->username} has updated the journal entry for \'<i>{$a->journalname}</i>\'<br /><br />
You can view the <a href="{$a->url}">journal entry here</a>.';
$string['mailsubject'] = 'Journal feedback';
$string['messageprovider:journal_feedback'] = 'Journal feedback from teacher';
$string['messageprovider:submission'] = 'Journal entry created or modified';
$string['modulename'] = 'Journal';
$string['modulename_help'] = '###### Key features
- Collect online text entries from students for review and grading
- Entries are private between student and teacher (not visible to other students)
- Supports feedback in text form and grading by the teacher
- Includes a "Days available" setting to control submission period
- Displays all entries for a class or group on one page for efficient review

###### Ways to use it
- Assign short reflective writing tasks or learning journals
- Use for ongoing feedback and iterative improvement of student work
- Encourage students to summarize lessons or readings in their own words
- Track student progress on simple text-based assignments
- Facilitate private communication between teacher and student for formative assessment';
$string['modulename_link'] = 'mod/journal/view';
$string['modulename_summary'] = 'Collects private online text entries from students for feedback and grading, with a set availability period and no file uploads.';
$string['modulenameplural'] = 'Journals';
$string['needsregrade'] = 'Entry has changed since last feedback was saved.';
$string['newjournalentries'] = 'New journal entries';
$string['nodatachanged'] = 'No data was changed.';
$string['nodeadline'] = 'Always open';
$string['noentriesmanagers'] = 'There are no teachers';
$string['noentry'] = 'No entry';
$string['nograde'] = 'No grade';
$string['noratinggiven'] = 'No rating given';
$string['notifystudents'] = 'Notify students';
$string['notifystudents_default'] = 'Notify students by default';
$string['notifystudents_default_help'] = 'Default setting for new Journal activities.';
$string['notifystudents_help'] = 'If enabled, students will receive a notification via Moodle messaging when a teacher provides feedback on a journal entry.';
$string['notifyteachers'] = 'Notify teachers';
$string['notifyteachers_default'] = 'Notify teachers by default';
$string['notifyteachers_default_help'] = 'Default setting for new Journal activities.';
$string['notifyteachers_help'] = 'If enabled, teachers will receive a notification via Moodle messaging when a student creates or updates a journal entry.';
$string['notopenuntil'] = 'This journal won\'t be open until';
$string['notstarted'] = 'You have not started this journal yet';
$string['numchars'] = '{$a} characters';
$string['overallrating'] = 'Overall rating';
$string['pluginadministration'] = 'Journal module administration';
$string['pluginname'] = 'Journal';
$string['privacy:metadata:journal_entries'] = 'A record of journal entry';
$string['privacy:metadata:journal_entries:entrycomment'] = 'The comment received by user to journal';
$string['privacy:metadata:journal_entries:modified'] = 'The start time of the journal entries.';
$string['privacy:metadata:journal_entries:rating'] = 'The rating received by user to journl';
$string['privacy:metadata:journal_entries:teacher'] = 'The teacher that has given feedback to user on journal';
$string['privacy:metadata:journal_entries:text'] = 'The text written by user';
$string['privacy:metadata:journal_entries:userid'] = 'The ID of the user';
$string['rate'] = 'Rate';
$string['removeentries'] = 'Remove all entries';
$string['removemessages'] = 'Remove all Journal entries';
$string['saveallfeedback'] = 'Save all my feedback';
$string['savechanges'] = 'Save changes';
$string['savefeedback'] = 'Save feedback';
$string['search:activity'] = 'Journal - activity information';
$string['search:entry'] = 'Journal - entries';
$string['showoverview'] = 'Show journals overview on my moodle';
$string['showrecentactivity'] = 'Show recent activity';
$string['started'] = 'You have started this journal';
$string['startoredit'] = 'Start or edit my journal entry';
$string['userswhocompletedthejournal'] = 'Users who completed the journal';
$string['userswhodidnotcompletedthejournal'] = 'Users who have not completed the journal';
$string['viewallentries'] = 'View {$a} journal entries';
$string['viewentries'] = 'View entries';
