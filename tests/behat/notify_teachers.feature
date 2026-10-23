@mod @mod_journal
Feature: Teachers choose when they are notified of entries
  In order to receive only the notifications I need
  As a teacher
  I need to choose when the journal notifies me of saved entries

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course1  | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: Teachers change when the journal notifies them
    Given the following "activities" exist:
      | activity | name              | intro            | course | idnumber | notifyteachers |
      | journal  | Test journal name | Journal question | C1     | journal1 | 2              |
    And I am on the "journal1" "Activity editing" page logged in as "teacher1"
    And the field "Notify teachers" matches value "When an entry needs grading"
    When I set the field "Notify teachers" to "Every time an entry is saved"
    And I press "Save and display"
    And I am on the "journal1" "Activity editing" page
    Then the field "Notify teachers" matches value "Every time an entry is saved"
