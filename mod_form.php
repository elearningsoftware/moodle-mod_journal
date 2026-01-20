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
 * Form structure for mod_journal
 *
 * @package mod_journal
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * The mod_journal_mod_form class.
 *
 * @package    mod_journal
 * @copyright  2022 Elearning Software SRL http://elearningsoftware.ro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_journal_mod_form extends moodleform_mod {
    /**
     * Set up the form definition.
     */
    public function definition() {
        global $COURSE;

        $mform = &$this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('journalname', 'journal'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('journalquestion', 'journal'));

        $options = [];
        $options[0] = get_string('alwaysopen', 'journal');
        for ($i = 1; $i <= 13; $i++) {
            $options[$i] = get_string('numdays', '', $i);
        }
        for ($i = 2; $i <= 16; $i++) {
            $days = $i * 7;
            $options[$days] = get_string('numweeks', '', $i);
        }
        $options[365] = get_string('numweeks', '', 52);
        $mform->addElement('select', 'days', get_string('daysavailable', 'journal'), $options);
        if ($COURSE->format === 'weeks') {
            $mform->setDefault('days', '7');
        } else {
            $mform->setDefault('days', '0');
        }

        // Notifications settings.
        $config = get_config('journal');

        $mform->addElement('checkbox', 'notifyteachers', get_string('notifyteachers', 'journal'));
        $mform->addHelpButton('notifyteachers', 'notifyteachers', 'journal');
        $mform->setDefault('notifyteachers', isset($config->notifyteachers_default) ? $config->notifyteachers_default : 1);

        $mform->addElement('checkbox', 'notifystudents', get_string('notifystudents', 'journal'));
        $mform->addHelpButton('notifystudents', 'notifystudents', 'journal');
        $mform->setDefault('notifystudents', isset($config->notifystudents_default) ? $config->notifystudents_default : 1);

        $this->standard_grading_coursemodule_elements();

        // Apply default grade from global settings.
        // We only apply this for new instances (when $this->_instance is empty).
        if (empty($this->_instance)) {
            $defaultgrade = isset($config->defaultgrade) ? $config->defaultgrade : false;
            // If the setting is missing, fallback to 100 (Standard Moodle default).
            if ($defaultgrade === false) {
                $defaultgrade = 100;
            }
            $mform->setDefault('grade', $defaultgrade);
        }

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Returns whether the custom completion rules are enabled.
     *
     * @param array $data form data
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        // Compatibility check: get_suffix only exists in Moodle 4.3+.
        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';
        return (!empty($data['completion_create_entry' . $suffix]));
    }

    /**
     * Adds the custom completion rules for mod_journal
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;

        // Compatibility check: get_suffix only exists in Moodle 4.3+.
        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';
        $fieldname = 'completion_create_entry' . $suffix;

        $mform->addElement('advcheckbox', $fieldname, get_string('completiondetail:completion_create_entry', 'journal'));

        $mform->setType($fieldname, PARAM_INT);
        $mform->hideIf($fieldname, 'completion', 'neq', COMPLETION_TRACKING_AUTOMATIC);

        return ([$fieldname]);
    }
}
