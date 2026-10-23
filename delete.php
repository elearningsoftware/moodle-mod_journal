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
 * Deletes an entry of a journal, after a confirmation.
 *
 * @package    mod_journal
 * @copyright  2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/journal/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$entryid = required_param('entryid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('journal', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$journal = $DB->get_record('journal', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/journal:deleteentries', $context);

$entry = $DB->get_record('journal_entries', ['id' => $entryid, 'journal' => $journal->id], '*', MUST_EXIST);
$user = core_user::get_user($entry->userid);
if (!groups_user_groups_visible($course, $entry->userid, $cm)) {
    throw new \moodle_exception('nopermissions', 'error', '', get_string('deleteentry', 'journal'));
}

$returnurl = new moodle_url('/mod/journal/report.php', ['id' => $cm->id]);
$fullname = $user ? fullname($user) : $entry->userid;

if ($confirm) {
    require_sesskey();
    \mod_journal\manager::create_from_coursemodule($cm)->delete_entry($entry);
    redirect(
        $returnurl,
        get_string('entrydeleted', 'journal', $fullname),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_url('/mod/journal/delete.php', ['id' => $cm->id, 'entryid' => $entry->id]);
$PAGE->navbar->add(get_string('entries', 'journal'), $returnurl);
$PAGE->navbar->add(get_string('deleteentry', 'journal'));
$PAGE->set_title(format_string($journal->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($journal);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('deleteentry', 'journal'));
echo $OUTPUT->confirm(
    get_string('deleteentryconfirm', 'journal', $fullname),
    new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]),
    $returnurl
);
echo $OUTPUT->footer();
