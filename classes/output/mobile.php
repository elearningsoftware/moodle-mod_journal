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

namespace mod_journal\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/journal/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/weblib.php');

/**
 * Mobile output class for the Moodle App.
 *
 * This class handles the data preparation for the Journal activity in the Moodle App.
 * It provides templates and data for the main view and the edit entry view.
 *
 * @package    mod_journal
 * @copyright  2025 adrian.emanuel.sarmas@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Returns the template and data for the main course view in the mobile app.
     *
     * This view handles:
     * 1. Displaying the journal description.
     * 2. For Teachers: Showing a list of student submissions with grading forms.
     * 3. For Students: Showing their existing entry (if any) and feedback, or a button to edit.
     *
     * @param array $args Arguments from the mobile app.
     *                    - cmid: Course Module ID.
     *                    - courseid: Course ID.
     * @return array The template data structure required by the Moodle App.
     */
    public static function mobile_course_view($args) {
        global $DB, $USER, $OUTPUT, $CFG;

        $cmid = (int) $args['cmid'];
        $courseid = (int) $args['courseid'];

        // Retrieve course module, course, and journal instance.
        $cm = get_coursemodule_from_id('journal', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $journal = $DB->get_record('journal', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Ensure proper authentication and context access.
        require_login($course, false, $cm);

        // Base data structure for the template.
        $data = [
            'cmid' => $cmid,
            'courseid' => $courseid,
            'journalid' => $journal->id,
            'intro' => format_module_intro('journal', $journal, $cm->id),
            'name' => format_string($journal->name),
            'userid' => $USER->id,
            'format' => FORMAT_HTML,
        ];

        // Check capabilities.
        $canadd = has_capability('mod/journal:addentries', $context);
        $canmanage = has_capability('mod/journal:manageentries', $context);

        // TEACHER VIEW LOGIC.
        if ($canmanage) {
            $data['isteacher'] = true;

            // Determine grouping.
            $currentgroup = groups_get_activity_group($cm, true);

            // Get list of users who have submitted entries.
            $users = journal_get_users_done($journal, $currentgroup);
            $submissions = [];

            // Prepare grading options for the dropdown.
            $grades = make_grades_menu($journal->grade);

            if ($users) {
                foreach ($users as $student) {
                    $entry = $DB->get_record('journal_entries', ['journal' => $journal->id, 'userid' => $student->id]);
                    if (!$entry) {
                        continue;
                    }

                    // Process text for display.
                    $entrytextraw = (string) ($entry->text ?? '');
                    $feedbackrawtext = (string) ($entry->entrycomment ?? '');

                    // Rewrite pluginfile URLs to ensure images load in the app.
                    $entrytext = file_rewrite_pluginfile_urls(
                        $entrytextraw,
                        'pluginfile.php',
                        $context->id,
                        'mod_journal',
                        'entry',
                        $entry->id
                    );

                    $feedbackhtml = file_rewrite_pluginfile_urls(
                        $feedbackrawtext,
                        'pluginfile.php',
                        $context->id,
                        'mod_journal',
                        'feedback',
                        $entry->id
                    );

                    // Convert HTML feedback to plain text for the simple textarea editor.
                    $feedbackplain = trim(html_to_text($feedbackhtml, 0, false));
                    $feedbackplain = str_replace("\xc2\xa0", ' ', $feedbackplain);

                    // Format grade options for the Mustache template.
                    $studentgradeoptions = [];
                    foreach ($grades as $val => $label) {
                        $studentgradeoptions[] = [
                            'val' => $val,
                            'label' => $label,
                            'selected' => ($val == $entry->rating) ? 'selected' : '',
                        ];
                    }

                    $submissions[] = [
                        'studentid' => $student->id,
                        'studentname' => fullname($student),
                        'studentpic' => $OUTPUT->user_picture($student, ['size' => 35]),
                        'timemodified' => userdate($entry->modified),
                        'text' => format_text($entrytext, $entry->format, ['context' => $context]),
                        'rating' => $entry->rating,
                        'entryid' => $entry->id,
                        'gradeoptions' => $studentgradeoptions,
                        'rating_minus_one' => ($entry->rating == -1) ? 'selected' : '', // Helper for "No Grade" selection.
                        'feedback_plain' => $feedbackplain, // Plain text for the <textarea>.
                        'journalid' => $journal->id,
                        'commentformat' => FORMAT_HTML,
                    ];
                }
            }

            $data['submissions'] = $submissions;
        }

        // STUDENT VIEW LOGIC.
        // Calculate open/close dates.
        $timenow = time();
        $timestart = 0;
        $timefinish = 0;
        $journalopen = false;

        if ($course->format == 'weeks' && $journal->days) {
            $modinfo = get_fast_modinfo($course);
            $section = $modinfo->get_section_info_by_id($cm->section);
            $sectionnum = $section->sectionnum;
            $timestart = $course->startdate + (($sectionnum - 1) * 604800);
            $timefinish = $timestart + (3600 * 24 * (int) $journal->days);
        } else {
            $journal->days = 0;
        }

        if ($journal->days == 0 || ($timenow > $timestart && $timenow < $timefinish)) {
            $journalopen = true;
        }

        // Populate warning messages if journal is closed or not open yet.
        if ($timenow < $timestart) {
            $data['warning'] = get_string('notopenuntil', 'journal') . ': ' . userdate($timestart);
        } else if ($journal->days && $timenow > $timefinish) {
            $data['warning'] = get_string('editingended', 'journal') . ': ' . userdate($timefinish);
        }

        // Fetch student entry.
        $entry = $DB->get_record('journal_entries', ['userid' => $USER->id, 'journal' => $journal->id]);

        if ($entry) {
            $data['hasentry'] = true;
            $data['lastedited'] = userdate($entry->modified);

            // Rewrite files for display.
            $text = file_rewrite_pluginfile_urls(
                (string) $entry->text,
                'pluginfile.php',
                $context->id,
                'mod_journal',
                'entry',
                $entry->id
            );
            $data['text'] = format_text($text, $entry->format, ['context' => $context]);

            // Check for feedback.
            if (!empty($entry->entrycomment) || (!empty($entry->rating) && $entry->rating != -1)) {
                $data['hasfeedback'] = true;

                $feedbacktext = file_rewrite_pluginfile_urls(
                    (string) $entry->entrycomment,
                    'pluginfile.php',
                    $context->id,
                    'mod_journal',
                    'feedback',
                    $entry->id
                );
                $data['feedbacktext'] = format_text($feedbacktext, FORMAT_HTML, ['context' => $context]);

                if ($teacher = $DB->get_record('user', ['id' => $entry->teacher])) {
                    $data['teachername'] = fullname($teacher);
                    $data['feedbackdate'] = userdate($entry->timemarked);
                    $data['teacherpic'] = $OUTPUT->user_picture($teacher, ['size' => 35]);
                }

                if (!empty($entry->rating) && $entry->rating != -1) {
                    $gradinginfo = grade_get_grades($course->id, 'mod', 'journal', $entry->journal, [$USER->id]);
                    if (!empty($gradinginfo->items[0]->grades[$USER->id]->str_long_grade)) {
                        $data['grade'] = $gradinginfo->items[0]->grades[$USER->id]->str_long_grade;
                    }
                }
            }
        } else {
            $data['hasentry'] = false;
        }

        // Determine if editing is allowed.
        $data['canedit'] = $canadd && $journalopen;
        if ($data['canedit'] && !empty($journal->days)) {
            $data['info'] = get_string('editingends', 'journal') . ': ' . userdate($timefinish);
        }

        // JS INJECTION:
        // ionViewWillEnter is a lifecycle hook in Ionic. We hook into it to force a content refresh
        // when the user navigates back to this page (e.g. from the Edit page).
        // This ensures the student sees their updated entry immediately.
        $js = '
            this.ionViewWillEnter = function() {
                this.refreshContent(false);
            };
        ';

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_journal/mobile_view', $data),
                ],
            ],
            'javascript' => $js,
            'otherdata' => json_encode([]),
        ];
    }

    /**
     * Student entry editor page.
     *
     * This page is opened via core-site-plugins-new-content from the main view.
     * It provides a simple textarea for the student to write their entry.
     *
     * @param array $args Arguments from the mobile app.
     *                    - cmid: Course Module ID.
     *                    - courseid: Course ID.
     * @return array The template data structure.
     */
    public static function mobile_entry_edit($args) {
        global $DB, $USER, $OUTPUT;

        $cmid = (int) $args['cmid'];
        $courseid = (int) $args['courseid'];

        $cm = get_coursemodule_from_id('journal', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $journal = $DB->get_record('journal', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        require_login($course, false, $cm);

        $canadd = has_capability('mod/journal:addentries', $context);

        // Calculate timing to ensure edit capability is still valid.
        $timenow = time();
        $timestart = 0;
        $timefinish = 0;
        $journalopen = false;

        if ($course->format == 'weeks' && $journal->days) {
            $modinfo = get_fast_modinfo($course);
            $section = $modinfo->get_section_info_by_id($cm->section);
            $sectionnum = $section->sectionnum;
            $timestart = $course->startdate + (($sectionnum - 1) * 604800);
            $timefinish = $timestart + (3600 * 24 * (int) $journal->days);
        } else {
            $journal->days = 0;
        }

        if ($journal->days == 0 || ($timenow > $timestart && $timenow < $timefinish)) {
            $journalopen = true;
        }

        $data = [
            'cmid' => $cmid,
            'courseid' => $courseid,
            'format' => FORMAT_MOODLE, // Using FORMAT_MOODLE (0) preserves newlines from the textarea properly.
            'canedit' => $canadd && $journalopen,
            'text_plain' => '',
            'journalid' => $journal->id, // Note: The WebService actually expects CMID in the journalid param, checked in template.
        ];

        // Warnings logic.
        if ($timenow < $timestart) {
            $data['warning'] = get_string('notopenuntil', 'journal') . ': ' . userdate($timestart);
        } else if ($journal->days && $timenow > $timefinish) {
            $data['warning'] = get_string('editingended', 'journal') . ': ' . userdate($timefinish);
        }

        if ($data['canedit'] && !empty($journal->days)) {
            $data['info'] = get_string('editingends', 'journal') . ': ' . userdate($timefinish);
        }

        // Load existing entry.
        $entry = $DB->get_record('journal_entries', ['userid' => $USER->id, 'journal' => $journal->id]);
        if ($entry) {
            $texthtml = file_rewrite_pluginfile_urls(
                (string) $entry->text,
                'pluginfile.php',
                $context->id,
                'mod_journal',
                'entry',
                $entry->id
            );

            // Convert HTML to Plain Text for the <textarea>.
            // We use html_to_text to convert <br> tags to actual newlines.
            $plain = trim(html_to_text($texthtml, 0, false));
            $plain = str_replace("\xc2\xa0", ' ', $plain);

            $data['text_plain'] = $plain;
        }

        // JS INJECTION:
        // Define 'goBack' on the component scope so the Cancel button works.
        // window.history.back() is the standard way to navigate back in the App.
        $js = '
            this.goBack = function() {
                window.history.back();
            };
        ';

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_journal/mobile_edit_entry', $data),
                ],
            ],
            'javascript' => $js,
            'otherdata' => json_encode([]),
        ];
    }
}
