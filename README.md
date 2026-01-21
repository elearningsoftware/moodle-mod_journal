# Moodle Journal module

[![Moodle Plugin CI](https://github.com/elearningsoftware/moodle-mod_journal/actions/workflows/main.yml/badge.svg?branch=master)](https://github.com/elearningsoftware/moodle-mod_journal/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amaster) [![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)

The Journal activity allows teachers to obtain student feedback or reflections on a specific topic. Students can edit and refine their entries over time, and teachers can provide private feedback and grades.

- **Documentation:** [MoodleDocs: Journal Module](http://docs.moodle.org/en/Journal_module)
- **Source Code:** [GitHub](https://github.com/elearningsoftware/moodle-mod_journal)
- **Plugin Directory:** [Moodle.org](https://moodle.org/plugins/mod_journal)

## Features
*   **Simple Interface:** A single page for students to write and refine their entry.
*   **Private:** Entries are visible only to the student and the teacher.
*   **Grading & Feedback:** Teachers can grade entries and provide feedback.
*   **Time Constraints:** Set availability windows (days/weeks).
*   **Mobile Support:** Fully compatible with the official Moodle Mobile App.
*   **Privacy API:** Full compliance with Moodle's GDPR privacy API.

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
1. Download the zip file from the [GitHub Releases page](https://github.com/elearningsoftware/moodle-mod_journal/releases) or the Moodle Plugin Directory.
2. Unzip the file.
3. Upload the resulting `journal` folder to your Moodle `mod/` directory.
4. Log in to your Moodle site as an administrator and go to **Site administration > Notifications** to complete the installation.

## Compatibility
This version is compatible with Moodle 4.0 and higher.

## Credits
*   Originally developed by Martin Dougiamas.
*   Maintained by [Elearning Software SRL](http://elearningsoftware.ro).
*   Various community contributors.

## License
This program is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License** as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.