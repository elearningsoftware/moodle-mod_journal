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
        global $DB, $CFG;

        $journalid = $event->objectid;
        $courseid = $event->courseid;
        $userid = $event->userid;
        $cmid = $event->contextinstanceid;

        $journal = $DB->get_record('journal', ['id' => $journalid], '*', MUST_EXIST);

        // Check if the instance setting is enabled.
        if (empty($journal->notifyteachers)) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('journal', $journalid, $courseid);
        $context = \context_module::instance($cm->id);

        // Get the user who submitted.
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        // Find teachers/managers in this context.
        if (class_exists('\core_user\fields')) {
            $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        } else {
            $userfields = get_all_user_name_fields(true, 'u');
        }

        // Changed from get_users_by_capability to get_enrolled_users to prevent site admins
        // receiving notifications unless they are explicitly enrolled in the course.
        $teachers = get_enrolled_users($context, 'mod/journal:manageentries', 0, 'u.id, u.email, u.lang, ' . $userfields);

        if (empty($teachers)) {
            return;
        }

        foreach ($teachers as $teacher) {
            // Don't notify yourself.
            if ($teacher->id == $userid) {
                continue;
            }

            // Check if the teacher can actually access this group (if separate groups).
            $groupmode = groups_get_activity_groupmode($cm, $course);
            if ($groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context, $teacher)) {
                $usergroups = groups_get_all_groups($course->id, $userid, $cm->groupingid);
                $teachergroups = groups_get_all_groups($course->id, $teacher->id, $cm->groupingid);
                $sharedgroups = array_intersect(array_keys($usergroups), array_keys($teachergroups));
                if (empty($sharedgroups)) {
                    continue;
                }
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
