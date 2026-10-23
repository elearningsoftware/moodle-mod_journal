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

namespace mod_journal\task;

use core\message\message;

/**
 * Reminds the students who have not written an entry that a journal closes soon.
 *
 * Each closing time is reminded of once. Students are reminded again when the closing time changes.
 *
 * @package    mod_journal
 * @copyright  2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_reminders extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('remindertask', 'mod_journal');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        $timenow = time();
        $sql = 'SELECT *
                  FROM {journal}
                 WHERE remindbefore > 0
                       AND timeclose > :timenow
                       AND timeclose - remindbefore <= :timenow2
                       AND timeopen <= :timenow3
                       AND remindedtimeclose <> timeclose';
        $journals = $DB->get_records_sql($sql, ['timenow' => $timenow, 'timenow2' => $timenow, 'timenow3' => $timenow]);

        foreach ($journals as $journal) {
            $cm = get_fast_modinfo($journal->course)->get_instances_of('journal')[$journal->id] ?? null;
            // Students are reminded once they can see the journal, if it is still open then.
            if (!$cm || !$cm->visible || !$cm->get_course()->visible) {
                continue;
            }

            mtrace("Reminding the students of journal {$journal->id}");
            $this->remind_students($journal, $cm);
            $DB->set_field('journal', 'remindedtimeclose', $journal->timeclose, ['id' => $journal->id]);
        }
    }

    /**
     * Send the reminders of a journal to its students who have not written an entry.
     *
     * @param \stdClass $journal The journal
     * @param \cm_info $cm Its course module
     */
    protected function remind_students(\stdClass $journal, \cm_info $cm): void {
        global $DB;

        $context = \context_module::instance($cm->id);
        $users = get_enrolled_users($context, 'mod/journal:addentries', 0, 'u.*', null, 0, 0, true);
        $users = array_diff_key(
            $users,
            get_users_by_capability($context, 'mod/journal:manageentries', 'u.id'),
            $DB->get_records('journal_entries', ['journal' => $journal->id], '', 'userid')
        );
        $users = (new \core_availability\info_module($cm))->filter_user_list($users);

        $course = $cm->get_course();
        $url = new \moodle_url('/mod/journal/view.php', ['id' => $cm->id]);
        foreach ($users as $user) {
            // Write the message in the language and time zone of the user.
            $oldlang = force_current_language($user->lang);

            $info = (object) [
                'journal' => format_string($journal->name, true, ['context' => $context]),
                'course' => format_string($course->fullname, true, ['context' => $context]),
                'timeclose' => userdate($journal->timeclose, '', \core_date::get_user_timezone($user)),
                'url' => $url->out(false),
            ];

            $message = new message();
            $message->courseid = $course->id;
            $message->component = 'mod_journal';
            $message->name = 'reminder';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $user;
            $message->subject = get_string('remindersubject', 'journal', $info->journal);
            $message->fullmessage = get_string('reminderbody', 'journal', $info);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = get_string('reminderbodyhtml', 'journal', $info);
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = $info->url;
            $message->contexturlname = $info->journal;

            message_send($message);

            force_current_language($oldlang);
        }
    }
}
