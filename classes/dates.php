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

use core\activity_dates;

/**
 * Opening and closing dates of a journal, shown on the course page and in the activity header.
 *
 * @package    mod_journal
 * @copyright  2026 Adrian Sarmas adrian.emanuel.sarmas@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates extends activity_dates {
    /**
     * Returns the opening and closing dates of the journal.
     *
     * @return array
     */
    protected function get_dates(): array {
        $timeopen = $this->cm->customdata['timeopen'] ?? null;
        $timeclose = $this->cm->customdata['timeclose'] ?? null;
        $now = time();
        $dates = [];

        if ($timeopen) {
            $dates[] = [
                'dataid' => 'timeopen',
                'label' => get_string($timeopen > $now ? 'activitydate:opens' : 'activitydate:opened', 'course'),
                'timestamp' => (int) $timeopen,
            ];
        }

        if ($timeclose) {
            $dates[] = [
                'dataid' => 'timeclose',
                'label' => get_string($timeclose > $now ? 'activitydate:closes' : 'activitydate:closed', 'course'),
                'timestamp' => (int) $timeclose,
            ];
        }

        return $dates;
    }
}
