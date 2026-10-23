@mod @mod_journal
Feature: Students see the journal question and closing time while writing
  In order to write my entry
  As a student
  I need to see what the journal asks and when it closes

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course1  | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | student1 | Student   | 1        | student1@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  Scenario: The edit page shows the journal question and closing time
    Given the following "activities" exist:
      | activity | name              | intro            | course | idnumber | timeclose   |
      | journal  | Test journal name | Journal question | C1     | journal1 | ##+2 days## |
    When I am on the "journal1" "Activity" page logged in as "student1"
    And I press "Start or edit my journal entry"
    Then I should see "Journal question"
    And I should see "Closes:"
