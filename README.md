# Moodle Journal module

[![Moodle Plugin CI](https://github.com/elearningsoftware/moodle-mod_journal/actions/workflows/main.yml/badge.svg?branch=master)](https://github.com/elearningsoftware/moodle-mod_journal/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amaster) [![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)

The Journal activity allows teachers to obtain student feedback or reflections on a specific topic. Students can edit and refine their entries over time, and teachers can provide private feedback and grades.

- **Documentation:** [MoodleDocs: Journal Module](http://docs.moodle.org/en/Journal_module)
- **Source Code:** [GitHub](https://github.com/elearningsoftware/moodle-mod_journal)
- **Plugin Directory:** [Moodle.org](https://moodle.org/plugins/mod_journal)

## Features
*   **Simple Interface:** A single page for students to write and refine their entry.
*   **Private:** Entries are visible only to the student and the teacher.
*   **Grading & Feedback:** Teachers can grade entries and provide feedback, all at once or one entry at a time, and filter the entries that need grading.
*   **Time Constraints:** Set opening and closing times, shown in the calendar and on the timeline, or availability windows (days/weeks) in weekly courses.
*   **Word Limits:** Set a minimum and a maximum number of words for entries.
*   **Reminders:** Students who have not written an entry can be reminded before the journal closes.
*   **Deleting Entries:** Teachers can delete an entry so that the student can start again.
*   **Notifications:** Teachers can be notified when an entry needs grading, every time one is saved, or not at all, and students are notified of their feedback.
*   **Completion:** Activities can be completed by writing an entry.
*   **Course Overview:** The course activities overview of Moodle 5.0 and later shows the number of entries and how many need grading.
*   **Mobile Support:** Fully compatible with the official Moodle Mobile App.
*   **Backup & Restore, Privacy API and Global Search:** Full support for Moodle's backup, GDPR privacy and search APIs.

## Installation

### Via Git (Recommended)
1. Navigate to your Moodle `mod` directory:
   ```bash
   cd /path/to/moodle/mod
   ```
2. Clone the repository:
   ```bash
   git clone https://github.com/elearningsoftware/moodle-mod_journal.git journal
   ```
3. Log in to your Moodle site as an administrator and go to **Site administration > Notifications** to complete the installation.

### Via Zip
1. Download the ZIP file from the [GitHub repository](https://github.com/elearningsoftware/moodle-mod_journal) (Click on the green **Code** button and select **Download ZIP**).
2. Unzip the file.
3. Rename the extracted folder to `journal` (GitHub downloads are often named `moodle-mod_journal-master`, so you must rename it manually).
4. Upload the `journal` folder to your Moodle `mod/` directory.
5. Log in to your Moodle site as an administrator and go to **Site administration > Notifications** to complete the installation.

## Compatibility
This version is compatible with Moodle 4.0 to 5.3.

## Changes
See [CHANGES.md](CHANGES.md).

## Credits
*   Originally developed by Martin Dougiamas.
*   Maintained by [Elearning Software SRL](http://elearningsoftware.ro).
*   Various community contributors.

## License
This program is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License** as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.