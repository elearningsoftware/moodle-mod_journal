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
 * Base class of the mod_journal external functions.
 *
 * The external API lives in the core_external namespace since Moodle 4.2 and the global class names
 * are deprecated since Moodle 5.3. Moodle 4.0 and 4.1 only have the global classes, so they are
 * aliased into core_external there, letting the external functions use the namespaced names everywhere.
 *
 * @package   mod_journal
 * @copyright 2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_journal\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

if (!class_exists('core_external\external_api')) {
    // Moodle 4.0 and 4.1.
    require_once($CFG->libdir . '/externallib.php');
    class_alias('external_api', 'core_external\external_api');
    class_alias('external_function_parameters', 'core_external\external_function_parameters');
    class_alias('external_single_structure', 'core_external\external_single_structure');
    class_alias('external_value', 'core_external\external_value');
    class_alias('external_settings', 'core_external\external_settings');
}

class_alias('core_external\external_api', 'mod_journal\external\journal_external_api_base');
