@mod @mod_journal @javascript
Feature: Teacher can save the feedback of a single entry
  In order to give feedback to students one at a time
  As a teacher
  I need to save the grade and feedback of one entry without saving the others

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course1  | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
      | student2 | Student   | 2        | student2@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
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

  Scenario: Teacher saves the feedback of one entry
    Given I am on the "journal1" "Activity" page logged in as "teacher1"
    And I follow "View 2 journal entries"
    And I set the field "Student 2 Grade" to "94"
    And I set the field "Student 2 Feedback" to "Well done"
    And I set the field "Student 1 Grade" to "22"
    When I click on "Save feedback" "button" in the "//div[contains(@class, 'journaluserentrywrapper')][contains(., 'Student 2 first reply')]" "xpath_element"
    Then I should see "Feedback updated for Student 2"
    And I reload the page
    And the field "Student 2 Grade" matches value "94"
    And the field "Student 2 Feedback" matches value "Well done"
    And the field "Student 1 Grade" matches value "No grade..."
    # The editor now wraps the feedback in a paragraph, which is saved once.
    And I click on "Save feedback" "button" in the "//div[contains(@class, 'journaluserentrywrapper')][contains(., 'Student 2 first reply')]" "xpath_element"
    And I wait "2" seconds
    And I click on "Save feedback" "button" in the "//div[contains(@class, 'journaluserentrywrapper')][contains(., 'Student 2 first reply')]" "xpath_element"
    And I should see "No data was changed."
