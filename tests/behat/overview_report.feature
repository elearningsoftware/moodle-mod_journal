@mod @mod_journal
Feature: Testing overview integration in journal activity
  In order to summarize the journal activity
  As a user
  I need to be able to see the journal activity overview

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
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
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test journal name"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | Student 1 first reply |
    And I press "Save changes"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test journal name"
    And I should see "Journal question"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | Student 2 first reply |
    And I press "Save changes"
    And I log out
    And I log in as "student3"
    And I am on "Course 1" course homepage
    And I follow "Test journal name"
    And I should see "Journal question"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | Student 3 first reply |
    And I press "Save changes"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage

  @javascript
  Scenario: The journal activity overview report should generate log events
    Given the site is running Moodle version 5.0 or higher
    And I am on the "Course 1" "course > activities > journal" page logged in as "teacher1"
    When I am on the "Course 1" "course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I click on "Get these logs" "button"
    Then I should see "Course activities overview page viewed"
    And I should see "viewed the instance list for the module 'journal'"

  @javascript
  Scenario: The journal activity index redirect to the activities overview
    Given the site is running Moodle version 5.0 or higher
    When I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Activities" block
    And I click on "Journals" "link" in the "Activities" "block"
    Then I should see "An overview of all activities in the course"
    And I should see "Name" in the "journal_overview_collapsible" "region"
    And I should see "Entries" in the "journal_overview_collapsible" "region"
    And I should see "Test journal name"
