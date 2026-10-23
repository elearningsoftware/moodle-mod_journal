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

namespace mod_journal;

use core\message\message;

/**
 * Event observer for mod_journal.
 *
 * @package    mod_journal
 * @copyright  2025 eDaktik GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_observer {
    /**
     * Triggered when an entry is created or updated.
     *
     * @param \core\event\base $event
     */
    public static function entry_modified(\core\event\base $event) {
        global $DB;

        $cm = get_coursemodule_from_id('journal', $event->contextinstanceid, $event->courseid);
        if (!$cm) {
            return;
        }
        $journal = $DB->get_record('journal', ['id' => $cm->instance], '*', MUST_EXIST);

        // Check if the instance setting is enabled.
        if (empty($journal->notifyteachers)) {
            return;
        }

        $course = get_course($event->courseid);
        $context = \context_module::instance($cm->id);

        // Get the user who submitted.
        $userid = $event->relateduserid;
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        // Find teachers/managers in this context.
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        // Changed from get_users_by_capability to get_enrolled_users to prevent site admins
        // receiving notifications unless they are explicitly enrolled in the course.
        $requiredfields = 'u.id, u.auth, u.mnethostid, u.username, u.email, u.emailstop, '
            . 'u.mailformat, u.maildisplay, u.lang, u.deleted, u.suspended';
        $teachers = get_enrolled_users($context, 'mod/journal:manageentries', 0, $requiredfields . ', ' . $userfields);

        if (empty($teachers)) {
            return;
        }

        // In separate groups mode, teachers who cannot access all groups must share a group with the user.
        $groupmembers = null;
        if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS) {
            $usergroups = groups_get_all_groups($course->id, $userid, $cm->groupingid);
            $groupmembers = $usergroups ? groups_get_groups_members(array_keys($usergroups)) : [];
        }

        foreach ($teachers as $teacher) {
            // Don't notify yourself.
            if ($teacher->id == $userid) {
                continue;
            }

            if (
                $groupmembers !== null && !isset($groupmembers[$teacher->id])
                && !has_capability('moodle/site:accessallgroups', $context, $teacher)
            ) {
                continue;
            }

            self::send_notification($user, $teacher, $journal, $course, $cm);
        }
    }

    /**
     * Helper to send the message.
     *
     * @param \stdClass $sender The student.
     * @param \stdClass $recipient The teacher.
     * @param \stdClass $journal The journal instance.
     * @param \stdClass $course The course.
     * @param \stdClass $cm The course module.
     */
    protected static function send_notification($sender, $recipient, $journal, $course, $cm) {
        global $CFG;

        $info = new \stdClass();
        $info->userid = $sender->id;
        $info->username = fullname($sender);
        $info->journalname = format_string($journal->name, true);
        $info->url = $CFG->wwwroot . '/mod/journal/report.php?id=' . $cm->id;

        $postsubject = get_string('mailsubject', 'journal') . ': ' . $info->journalname;
        $posttext = get_string('mailbody', 'journal', $info);
        $posthtml = get_string('mailbodyhtml', 'journal', $info);

        $eventdata = new message();
        $eventdata->courseid = $course->id;
        $eventdata->component = 'mod_journal';
        $eventdata->name = 'submission';
        $eventdata->userfrom = $sender;
        $eventdata->userto = $recipient;
        $eventdata->subject = $postsubject;
        $eventdata->fullmessage = $posttext;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = $posthtml;
        $eventdata->smallmessage = $postsubject;
        $eventdata->contexturl = $info->url;
        $eventdata->contexturlname = $info->journalname;

        message_send($eventdata);
    }
}
