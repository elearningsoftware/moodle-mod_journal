@mod @mod_journal @javascript
Feature: Teacher can see which entries need grading
  In order to grade new and changed entries
  As a teacher
  I need to see and filter the entries that need grading

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course1  | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
      | student2 | Student   | 2        | student2@asd.com |
      | student3 | Student   | 3        | student3@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "activities" exist:
      | activity | name              | intro            | course | idnumber |
      | journal  | Test journal name | Journal question | C1     | journal1 |
    And I am on the "journal1" "Activity" page logged in as "student1"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | Student 1 first reply |
    And I press "Save changes"
    And I am on the "journal1" "Activity" page logged in as "student2"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | Student 2 first reply |
    And I press "Save changes"

  Scenario: Teacher filters the entries that need grading
    Given I am on the "journal1" "Activity" page logged in as "teacher1"
    And I follow "View 2 journal entries"
    And "Needs grading" "text" should exist in the "//div[contains(@class, 'journaluserentrywrapper')][contains(., 'Student 1 first reply')]" "xpath_element"
    And "Needs grading" "text" should exist in the "//div[contains(@class, 'journaluserentrywrapper')][contains(., 'Student 2 first reply')]" "xpath_element"
    And I should see "Student 3"
    When I set the field "Show" to "Needs grading (2)"
    Then I should see "Student 1 first reply"
    And I should see "Student 2 first reply"
    And I should not see "Student 3"
    And I set the field "Student 1 Grade" to "80"
    And I press "Save all my feedback"
    And I should see "Feedback updated for 1 entries"
    And the field "Show" matches value "Needs grading (1)"
    And I should not see "Student 1 first reply"
    And I should see "Student 2 first reply"
    And I set the field "Student 2 Grade" to "70"
    And I press "Save all my feedback"
    And I should see "No entries need grading."
