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
 * mod_journal settings page
 *
 * @package    mod_journal
 * @copyright  2014 David Monllao <david.monllao@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configselect(
        'journal/showrecentactivity',
        get_string('showrecentactivity', 'journal'),
        get_string('showrecentactivity', 'journal'),
        0,
        ['0' => get_string('no'), '1' => get_string('yes')]
    ));

    $settings->add(new admin_setting_configselect(
        'journal/overview',
        get_string('showoverview', 'journal'),
        get_string('showoverview', 'journal'),
        1,
        ['0' => get_string('no'), '1' => get_string('yes')]
    ));

    // Setting: Default Grade.
    $settings->add(new admin_setting_configtext(
        'journal/defaultgrade',
        get_string('defaultgrade', 'journal'),
        get_string('defaultgrade_help', 'journal'),
        100,
        PARAM_INT
    ));

    // Setting: Default Notify Teachers.
    $settings->add(new admin_setting_configcheckbox(
        'journal/notifyteachers_default',
        get_string('notifyteachers_default', 'journal'),
        get_string('notifyteachers_default_help', 'journal'),
        1
    ));

    // Setting: Default Notify Students.
    $settings->add(new admin_setting_configcheckbox(
        'journal/notifystudents_default',
        get_string('notifystudents_default', 'journal'),
        get_string('notifystudents_default_help', 'journal'),
        1
    ));
}
