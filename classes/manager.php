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

use cm_info;
use context_module;
use stdClass;

/**
 * Class manager for journal activity
 *
 * @package   mod_journal
 * @copyright 2025 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** Module name. */
    public const MODULE = 'journal';

    /** @var context_module the current context. */
    private $context;

    /** @var stdClass $course record. */
    private $course;

    /** @var \moodle_database the database instance. */
    private \moodle_database $db;

    /** @var cm_info the course module. */
    private cm_info $cm;

    /** @var stdClass the instance record. */
    private stdClass $instance;

    /**
     * Resolve the moodle_database instance.
     *
     * Moodle 4.5+ ships a DI container at \core\di. On older Moodles we
     * fall back to the global $DB so the plugin keeps working down to
     * Moodle 4.0 (its declared minimum).
     *
     * @return \moodle_database
     */
    private static function db(): \moodle_database {
        if (class_exists('\\core\\di')) {
            return \core\di::get(\moodle_database::class);
        }
        global $DB;
        return $DB;
    }

    /**
     * Class constructor.
     *
     * @param cm_info $cm course module info object
     * @param stdClass $instance activity instance object.
     */
    public function __construct(
        cm_info $cm,
        stdClass $instance
    ) {
        $this->cm = $cm;
        $this->instance = $instance;
        $this->context = context_module::instance($cm->id);
        $this->db = self::db();
        $this->course = $cm->get_course();
    }

    /**
     * Create a manager instance from an instance record.
     *
     * @param stdClass $instance an activity record
     * @return manager
     */
    public static function create_from_instance(stdClass $instance): self {
        $cm = get_coursemodule_from_instance(self::MODULE, $instance->id);
        // Ensure that $this->cm is a cm_info object.
        $cm = cm_info::create($cm);
        return new self($cm, $instance);
    }

    /**
     * Create a manager instance from a course_modules record.
     *
     * @param stdClass|cm_info $cm an activity record
     * @return manager
     */
    public static function create_from_coursemodule($cm): self {
        // Ensure that $this->cm is a cm_info object.
        $cm = cm_info::create($cm);
        $db = self::db();
        $instance = $db->get_record(self::MODULE, ['id' => $cm->instance], '*', MUST_EXIST);
        return new self($cm, $instance);
    }

    /**
     * Return the current context.
     *
     * @return context_module
     */
    public function get_context(): context_module {
        return $this->context;
    }

    /**
     * Return the current instance.
     *
     * @return stdClass the instance record
     */
    public function get_instance(): stdClass {
        return $this->instance;
    }

    /**
     * Return the current cm_info.
     *
     * @return cm_info the course module
     */
    public function get_coursemodule(): cm_info {
        return $this->cm;
    }

    /**
     * Check if the current user has added an entry in the journal.
     *
     * @return bool true if the user has answered, false otherwise
     */
    public function has_answered(): bool {
        global $DB, $USER;
        return $DB->record_exists('journal_entries', [
            'journal' => $this->instance->id,
            'userid' => $USER->id,
        ]);
    }

    /**
     * Return the count of users who can submit entries to this journal module, that the current user can see.
     *
     * @param int[] $groupids the group identifiers to filter by, empty array means no filtering
     * @return int the number of answers that the user can see
     */
    public function count_all_users(
        array $groupids = []
    ): int {
        if (!has_capability('mod/journal:addentries', $this->context)) {
            return 0;
        }

        // Get all users with the capability to choose in this context.
        $users = get_users_by_capability($this->context, 'mod/journal:addentries', 'u.id');

        if (empty($users)) {
            return 0;
        }

        // No group filtering requested: simply count users with the capability.
        if (empty($groupids)) {
            return count($users);
        }

        // With group filtering: count users that are in any of the requested groups
        // or that are not in any group (group 0 semantics).
        $groupids = array_unique($groupids);
        $count = 0;
        foreach ($users as $userid => $unused) {
            $usergroups = groups_get_user_groups($this->course->id, $userid);
            $ug = isset($usergroups[0]) ? $usergroups[0] : [];
            if (!empty(array_intersect($ug, $groupids)) || empty($ug)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Return the current count of users who have submitted entries to this journal module, that the current user can see.
     *
     * @param int[] $groupids the group identifiers to filter by, empty array means no filtering
     * @return int the number of entries that the user can see
     */
    public function count_all_users_answered(
        array $groupids = []
    ): int {
        if (!has_capability('mod/journal:manageentries', $this->context)) {
            return 0;
        }
        // Check to see if groups are being used in this journal.
        $groupmode = groups_get_activity_groupmode($this->cm);

        if ($groupmode) {
            groups_get_activity_group($this->cm, true);
        }

        if (empty($groupids)) {
            $groupids = 0;
        }
        $entrycount = journal_count_entries($this->instance, $groupids);

        return $entrycount;
    }

    /**
     * Count the users whose entries need grading, because they were never graded or were edited since.
     *
     * @param int[] $groupids The groups to count the users of, empty for all users
     * @return int
     */
    public function count_all_users_needing_grading(array $groupids = []): int {
        global $CFG;
        require_once($CFG->dirroot . '/mod/journal/lib.php');

        if (!has_capability('mod/journal:manageentries', $this->context)) {
            return 0;
        }

        return journal_count_entries($this->instance, $groupids ?: 0, true);
    }

    /**
     * Return the options of the editor of the entries.
     *
     * @return array
     */
    public function get_entry_editor_options(): array {
        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => $this->context,
            'subdirs' => false,
            'enable_filemanagement' => true,
        ];
    }

    /**
     * Save the entry of the current user, creating it if needed.
     *
     * Moves the files of the draft area into the entry, triggers the entry created or updated event
     * and updates the completion state.
     *
     * @param string $text The entry text
     * @param int $format The format of the text
     * @param int $draftitemid The draft area of the files in the text, 0 for none
     * @return stdClass The saved entry
     */
    public function save_entry(string $text, int $format, int $draftitemid = 0): stdClass {
        global $USER;

        $transaction = $this->db->start_delegated_transaction();

        $entry = $this->db->get_record('journal_entries', ['journal' => $this->instance->id, 'userid' => $USER->id]);
        $isnew = !$entry;
        if ($isnew) {
            // The entry needs an ID before the files of the draft area can be saved.
            $entry = (object) [
                'journal' => $this->instance->id,
                'userid' => $USER->id,
                'text' => '',
                'format' => $format,
                'modified' => time(),
            ];
            $entry->id = $this->db->insert_record('journal_entries', $entry);
        }

        if ($draftitemid) {
            $text = file_save_draft_area_files(
                $draftitemid,
                $this->context->id,
                'mod_journal',
                'entry',
                $entry->id,
                $this->get_entry_editor_options(),
                $text
            );
        }

        $entry->text = $text;
        $entry->format = $format;
        $entry->modified = time();
        $this->db->update_record('journal_entries', $entry);

        $eventclass = $isnew ? event\entry_created::class : event\entry_updated::class;
        $event = $eventclass::create([
            'objectid' => $entry->id,
            'relateduserid' => $entry->userid,
            'context' => $this->context,
        ]);
        $this->add_event_snapshots($event);
        $event->trigger();

        $transaction->allow_commit();

        $completion = new \completion_info($this->course);
        if ($completion->is_enabled($this->cm) && $this->instance->completion_create_entry) {
            $completion->update_state($this->cm, COMPLETION_COMPLETE);
        }

        return $entry;
    }

    /**
     * Save the grade and feedback of an entry, if they changed.
     *
     * Updates the gradebook and triggers the feedback updated event. The given entry is updated too.
     *
     * @param stdClass $entry An entry of this journal
     * @param int $grade The grade, -1 for no grade
     * @param string $feedback The feedback, in HTML
     * @param int $draftitemid The draft area of the files in the feedback, 0 for none
     * @return bool Whether the grade or the feedback changed
     * @throws \invalid_parameter_exception If the grade is not in the grade menu of this journal
     */
    public function save_feedback(stdClass $entry, int $grade, string $feedback, int $draftitemid = 0): bool {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/mod/journal/lib.php');

        if ($entry->journal != $this->instance->id) {
            throw new \coding_exception('The entry is not from this journal');
        }

        $grade = max($grade, -1);
        $oldgrade = (int) ($entry->rating ?? -1);
        // A locked grade is sent back unchanged, even if it is no longer valid.
        if ($grade !== $oldgrade && !journal_is_valid_grade($this->instance, $grade)) {
            throw new \invalid_parameter_exception('Invalid grade: ' . $grade);
        }

        $feedback = clean_text($feedback, FORMAT_HTML);
        if ($draftitemid) {
            $feedback = file_save_draft_area_files(
                $draftitemid,
                $this->context->id,
                'mod_journal',
                'feedback',
                $entry->id,
                [],
                $feedback
            );
        }

        if ($grade === $oldgrade && $feedback === (string) $entry->entrycomment) {
            return false;
        }

        $entry->rating = $grade;
        $entry->entrycomment = $feedback;
        $entry->teacher = $USER->id;
        $entry->timemarked = time();
        $entry->mailed = 0;

        $transaction = $this->db->start_delegated_transaction();

        $this->db->update_record('journal_entries', (object) [
            'id' => $entry->id,
            'rating' => $entry->rating,
            'entrycomment' => $entry->entrycomment,
            'teacher' => $entry->teacher,
            'timemarked' => $entry->timemarked,
            'mailed' => $entry->mailed,
        ]);

        $journal = clone $this->instance;
        $journal->cmidnumber = $this->cm->idnumber;
        journal_update_grades($journal, $entry->userid);

        $event = event\feedback_updated::create([
            'objectid' => $entry->id,
            'relateduserid' => $entry->userid,
            'context' => $this->context,
        ]);
        $this->add_event_snapshots($event);
        $event->trigger();

        $transaction->allow_commit();

        return true;
    }

    /**
     * Add the records of the course, course module and journal to an event.
     *
     * @param \core\event\base $event
     */
    private function add_event_snapshots(\core\event\base $event): void {
        $event->add_record_snapshot('course_modules', $this->cm->get_course_module_record());
        $event->add_record_snapshot('course', $this->course);
        $event->add_record_snapshot('journal', $this->instance);
    }
}
