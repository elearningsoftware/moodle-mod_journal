@mod @mod_journal
Feature: Teachers can limit the number of words of entries
  In order to get entries of the length I ask for
  As a teacher
  I need to set the minimum and maximum number of words of entries

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
      | activity | name              | intro            | course | idnumber | minwords | maxwords |
      | journal  | Test journal name | Journal question | C1     | journal1 | 3        | 5        |

  Scenario: Students can only save entries within the limits
    Given I am on the "journal1" "Activity" page logged in as "student1"
    And I press "Start or edit my journal entry"
    And I should see "Between 3 and 5"
    When I set the field "Entry" to "Too short"
    And I press "Save changes"
    Then I should see "Your entry has 2 words, but it needs at least 3."
    And I set the field "Entry" to "One two three four five six"
    And I press "Save changes"
    And I should see "Your entry has 6 words, but it can have at most 5."
    And I set the field "Entry" to "Long enough now"
    And I press "Save changes"
    And I should see "Long enough now"
    And I should see "3 words"

  Scenario: The maximum number of words cannot be below the minimum
    Given I am on the "journal1" "Activity editing" page logged in as "teacher1"
    When I set the following fields to these values:
      | Minimum number of words | 10 |
      | Maximum number of words | 5  |
    And I press "Save and display"
    Then I should see "The maximum number of words must be at least the minimum number of words."
    And I set the following fields to these values:
      | Minimum number of words | -1 |
      | Maximum number of words | 0  |
    And I press "Save and display"
    And I should see "The number of words cannot be negative."
