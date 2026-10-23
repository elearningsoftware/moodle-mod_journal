@mod @mod_journal
Feature: Teachers can delete entries
  In order to let a student start again or remove an inappropriate entry
  As a teacher
  I need to delete journal entries

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course1  | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | teacher2 | Teacher   | 2        | teacher2@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
      | student2 | Student   | 2        | student2@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | name              | intro            | course | idnumber |
      | journal  | Test journal name | Journal question | C1     | journal1 |
    And I am on the "journal1" "Activity" page logged in as "student1"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | Student 1 reply |
    And I press "Save changes"
    And I am on the "journal1" "Activity" page logged in as "student2"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | Student 2 reply |
    And I press "Save changes"

  Scenario: Teacher deletes an entry and the student can write a new one
    Given I am on the "journal1" "Activity" page logged in as "teacher1"
    And I follow "View 2 journal entries"
    When I click on "Delete entry" "link" in the "//div[contains(@class, 'journaluserentrywrapper')][contains(., 'Student 1 reply')]" "xpath_element"
    Then I should see "Delete the entry of Student 1?"
    And I press "Cancel"
    And I should see "Student 1 reply"
    And I click on "Delete entry" "link" in the "//div[contains(@class, 'journaluserentrywrapper')][contains(., 'Student 1 reply')]" "xpath_element"
    And I press "Continue"
    And I should see "The entry of Student 1 was deleted."
    And I should not see "Student 1 reply"
    And I should see "Student 2 reply"
    And I am on the "journal1" "Activity" page logged in as "student1"
    And I should see "Start or edit my journal entry"
    And I should not see "Student 1 reply"

  Scenario: Non-editing teachers cannot delete entries
    Given I am on the "journal1" "Activity" page logged in as "teacher2"
    When I follow "View 2 journal entries"
    Then I should see "Student 1 reply"
    And "Delete entry" "link" should not exist
