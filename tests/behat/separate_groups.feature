@mod @mod_journal
Feature: Journal entries respect separate groups
  In order to keep entries private between groups
  As a teacher without access to all groups
  I must not see entries of students whose groups I am not in

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@asd.com |
      | student1 | Student | 1 | student1@asd.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | teacher |
      | student1 | C1 | student |
    And the following "groups" exist:
      | name | course | idnumber |
      | Group 1 | C1 | G1 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
    And the following "activities" exist:
      | activity | name               | intro            | course | idnumber | groupmode |
      | journal  | Test journal name  | Journal question | C1     | journal1 | 1         |
    And I log in as "student1"
    And I am on "Course1" course homepage
    And I follow "Test journal name"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | Student 1 first reply |
    And I press "Save changes"
    And I log out

  Scenario: Non-editing teacher in no group cannot see entries in separate groups mode
    When I log in as "teacher1"
    And I am on "Course1" course homepage
    And I follow "Test journal name"
    And I follow "View 0 journal entries"
    Then I should see "Sorry, but you need to be part of a group to see this page."
    And I should not see "Student 1 first reply"
