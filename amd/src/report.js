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
 * Javascript module for the report page
 *
 * @module      mod_journal/report
 * @copyright   2022 elearning & software srl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import {add as addToast} from 'core/toast';

/**
 * Save the feedback and grade of one entry.
 *
 * @param {HTMLElement} button The save button of the entry
 * @returns {Promise}
 */
const saveFeedback = (button) => {
    const entryid = button.dataset.entryid;
    const feedback = document.querySelector('[name="c' + entryid + '[text]"]');
    const grade = document.getElementById('r' + entryid);

    return fetchMany([{
        methodname: 'mod_journal_save_feedback',
        args: {
            cmid: parseInt(button.dataset.cmid),
            entryid: parseInt(entryid),
            userid: parseInt(button.dataset.userid),
            grade: grade && grade.value !== '' ? parseInt(grade.value) : -1,
            feedback: feedback ? feedback.value : '',
            itemid: parseInt(button.dataset.itemid),
        },
    }])[0].then(result => {
        if (result.changed) {
            return getString('feedbackupdatedforuser', 'mod_journal', button.dataset.fullname);
        }
        return getString('nodatachanged', 'mod_journal');
    }).then(addToast);
};

export const init = () => {
    document.addEventListener('click', event => {
        const button = event.target.closest('.saveindividualfeedback');
        if (!button) {
            return;
        }
        event.preventDefault();
        // Let the editor update the textarea with its content.
        button.dispatchEvent(new CustomEvent('form:editorUpdated'));
        saveFeedback(button).catch(Notification.exception);
    });
};
