@mod @mod_journal @core_completion @javascript
Feature: Journal activity completion
  In order to let students track their progress
  As a teacher
  I want to set journal completion based on entry creation

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1 | 0 | 1 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  Scenario: Completion condition - create an entry
    Given the following "activities" exist:
      | activity | name       | intro      | course | idnumber | completion | completionview | completion_create_entry |
      | journal  | My Journal | Desc       | C1     | journal1 | 2          | 0              | 1                       |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And the journal activity "My Journal" should be marked as not complete
    And I follow "My Journal"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | My first entry |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    Then the journal activity "My Journal" should be marked as complete
