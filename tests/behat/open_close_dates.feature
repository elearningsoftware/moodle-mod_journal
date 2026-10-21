@mod @mod_journal
Feature: Teachers can set when a journal opens and closes
  In order to give students a period to write their entries
  As a teacher
  I need to set the opening and closing times of a journal

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course1  | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name              | intro            | course | idnumber |
      | journal  | Test journal name | Journal question | C1     | journal1 |

  Scenario: Students cannot edit their entry before the journal opens
    Given I am on the "journal1" "Activity editing" page logged in as "teacher1"
    And I set the following fields to these values:
      | timeopen[enabled] | 1              |
      | Open the journal  | ##+2 days## |
    And I press "Save and display"
    And I should see "Opens:"
    When I am on the "journal1" "Activity" page logged in as "student1"
    Then I should see "This journal won't be open until"
    And "Start or edit my journal entry" "button" should not exist

  Scenario: Students cannot edit their entry after the journal closes
    Given the following "activities" exist:
      | activity | name           | intro            | course | idnumber | timeopen        | timeclose       |
      | journal  | Closed journal | Journal question | C1     | journal2 | ##-2 days## | ##-1 days## |
    When I am on the "journal2" "Activity" page logged in as "student1"
    Then I should see "Closed:"
    And I should see "Editing period has ended"
    And "Start or edit my journal entry" "button" should not exist

  Scenario: The closing time must be after the opening time
    Given I am on the "journal1" "Activity editing" page logged in as "teacher1"
    And I set the following fields to these values:
      | timeopen[enabled]  | 1           |
      | Open the journal   | ##+2 days## |
      | timeclose[enabled] | 1           |
      | Close the journal  | ##+1 days## |
    When I press "Save and display"
    Then I should see "The closing time must be after the opening time."
