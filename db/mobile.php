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
 * Mobile app definition.
 *
 * @package   mod_journal
 * @copyright 2025 adrian.emanuel.sarmas@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_journal' => [
        'handlers' => [
            'mod_journal' => [
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/journal/pix/icon.svg',
                    'class' => '',
                ],
                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view',
            ],
        ],
        'lang' => [
            ['journalname', 'mod_journal'],
            ['journalquestion', 'mod_journal'],
            ['startoredit', 'mod_journal'],
            ['savefeedback', 'mod_journal'],
            ['saveallfeedback', 'mod_journal'],
            ['feedback', 'mod_journal'],
            ['grade', 'mod_journal'],
            ['lastedited', 'mod_journal'],
            ['noentry', 'mod_journal'],
            ['notstarted', 'mod_journal'],
            ['notopenuntil', 'mod_journal'],
            ['editingends', 'mod_journal'],
            ['editingended', 'mod_journal'],
            ['blankentry', 'mod_journal'],
            ['needsregrade', 'mod_journal'],
            ['entries', 'mod_journal'],
            ['noentriesmanagers', 'mod_journal'],
            ['entry', 'mod_journal'],
            ['gradedby', 'mod_journal'],
            ['nograde', 'mod_journal'],
            ['changessaved', 'mod_journal'],
        ],
        'css' => $CFG->wwwroot . '/mod/journal/styles.css',
    ],
];
