# Changes

## Unreleased

### New features
- Teachers can grade entries one by one from the report page, moving to
  the previous and next entries and saving the feedback of each before
  showing the next one.
- Journals can have a minimum and a maximum number of words. Entries
  outside the limits cannot be saved, also from the mobile app.
- Teachers can choose to be notified only when an entry needs grading,
  that is when it is created and the first time it changes after it was
  graded. This is the default for new journals. Existing journals keep
  notifying teachers every time an entry is saved.
- The report page shows 20 users per page by default, with a choice of
  10, 50, 100 or all.

### Fixes
- Students see a grade of 0 on the journal page and in the mobile app,
  and are notified of it.
- In separate groups mode, graders only find the entries of their groups
  in global search, and graders who are in no group see no entries in the
  mobile app, as on the report page.
- Restoring a journal graded with a course scale uses the restored scale,
  and restored entries no longer notify students of their feedback again.
- The privacy export no longer loses entries of graders who graded in
  several journals, and includes the files of the entries and feedback.
- Entries and feedback saved unchanged from the mobile app keep their
  formatting and images, and changed feedback keeps its line breaks.
- Saving all feedback on the report page no longer fails when some grades
  are locked or overridden in the gradebook, and keeps their feedback. The
  individual save button is no longer shown for these grades.
- The feedback of a teacher whose account no longer exists is still shown.
- Sorting the report by date lists the users without an entry last.
- The feedback notifications no longer compare text columns in SQL, which
  fails on Oracle.
- Managers can call `mod_journal_view_journal`, and `mod_journal_get_entry`
  returns feedback with working file URLs.
- Words in separate paragraphs are counted separately.
- The course activities overview no longer counts teachers among the
  users expected to write an entry.

### For developers
- Entries and feedback are saved by `\mod_journal\manager::save_entry()`
  and `save_feedback()`, used by the forms and web services alike.
- The `entry_created`, `entry_updated` and `feedback_updated` events refer
  to the entry, in the `journal_entries` table, and to the user it belongs
  to as `relateduserid`, instead of the journal. The `entry_created` and
  `entry_updated` events have `needsgrading` in their other data, true if
  the entry started needing grading with the save.
- The `notifyteachers` field of journals is one of the
  `\mod_journal\manager::NOTIFY_` constants instead of 0 or 1. Existing
  values keep their meaning.
- `mod_journal_save_feedback` takes an optional `feedbackformat`,
  `FORMAT_HTML` by default or `FORMAT_PLAIN`.
- `mod_journal_set_text` throws an exception when the entry has too few
  or too many words.

## 5.4.0 (2026-10-21)

Supports Moodle 4.0 to 5.3.

### New features
- Journals can have opening and closing times, in any course format. They
  take precedence over the days available, and are shown on the course
  page, in the calendar and on the timeline.
- Entries that need grading have a badge on the report page, which can be
  filtered to show only them. The course activities overview of Moodle 5.0
  and later shows how many entries need grading.

### Fixes
- The editing period is now enforced when saving entries, also through the
  mobile app, and is calculated correctly before Moodle 4.4.
- Students can open the files of their own entries.
- Deleting user data through the privacy API also deletes their files.
- Resetting a course removes journal grades, and moves the opening and
  closing times with the course start date.
- Each user can only have one entry per journal. The upgrade removes
  duplicate entries, keeping the most recently modified one.
- Grades outside the grade menu of a journal are rejected.
- The cron task no longer processes the same skipped entries every run.
- Feedback is passed to the gradebook as HTML.
- Several error messages show the right text.

### Other changes
- The save feedback button uses the `mod_journal_save_feedback` web
  service.
- `mod_journal_set_text` accepts the course module ID as `cmid`.
- The notifications task runs every five minutes.
- Code for Moodle versions before 4.0 was removed.

## Earlier versions

See the [releases on GitHub](https://github.com/elearningsoftware/moodle-mod_journal/releases).
