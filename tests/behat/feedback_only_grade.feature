@mod @mod_journal
Feature: Students see teacher feedback when only a grade is saved
  In order to know my grade
  As a student
  I need to see the feedback block even when the teacher only gave a numeric grade

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course1  | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | 1        | teacher1@asd.com  |
      | student1 | Student   | 1        | student1@asd.com  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name              | intro            | course | idnumber |
      | journal  | Test journal name | Journal question | C1     | journal1 |
    And I log in as "student1"
    And I am on "Course1" course homepage
    And I follow "Test journal name"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | My first reply |
    And I press "Save changes"
    And I log out

  Scenario: Teacher saves only a grade with no comment
    When I log in as "teacher1"
    And I am on "Course1" course homepage
    And I follow "Test journal name"
    And I follow "View 1 journal entries"
    And I set the field "Student 1 Grade" to "75"
    # No comment is entered for this student.
    And I press "Save all my feedback"
    Then I should see "Feedback updated for 1 entries"
    And I log out
    And I log in as "student1"
    And I am on "Course1" course homepage
    And I follow "Test journal name"
    # The Feedback heading and the teacher's grade string (75.00 out of 100.00) must be visible
    # even though no comment was left. The previous buggy predicate only showed feedback
    # when entrycomment was non-empty, swallowing grade-only feedback.
    Then I should see "Feedback"
    And I should see "75.00"

  Scenario: Teacher saves a grade of 0 with no comment
    When I log in as "teacher1"
    And I am on "Course1" course homepage
    And I follow "Test journal name"
    And I follow "View 1 journal entries"
    And I set the field "Student 1 Grade" to "0"
    And I press "Save all my feedback"
    Then I should see "Feedback updated for 1 entries"
    And I log out
    And I log in as "student1"
    And I am on "Course1" course homepage
    And I follow "Test journal name"
    Then I should see "Feedback"
    And I should see "0.00"
    And I should see "Teacher 1"
