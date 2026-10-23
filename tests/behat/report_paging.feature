@mod @mod_journal @javascript
Feature: Teacher can page through the journal entries
  In order to grade journals of large courses
  As a teacher
  I need to see the entries one page at a time

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course1  | C1        | 0        |
    And the following "users" exist:
      | username  | firstname | lastname | email             |
      | teacher1  | Teacher   | 1        | teacher1@asd.com  |
      | student01 | Student   | 01       | student01@asd.com |
      | student02 | Student   | 02       | student02@asd.com |
      | student03 | Student   | 03       | student03@asd.com |
      | student04 | Student   | 04       | student04@asd.com |
      | student05 | Student   | 05       | student05@asd.com |
      | student06 | Student   | 06       | student06@asd.com |
      | student07 | Student   | 07       | student07@asd.com |
      | student08 | Student   | 08       | student08@asd.com |
      | student09 | Student   | 09       | student09@asd.com |
      | student10 | Student   | 10       | student10@asd.com |
      | student11 | Student   | 11       | student11@asd.com |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | teacher1  | C1     | editingteacher |
      | student01 | C1     | student        |
      | student02 | C1     | student        |
      | student03 | C1     | student        |
      | student04 | C1     | student        |
      | student05 | C1     | student        |
      | student06 | C1     | student        |
      | student07 | C1     | student        |
      | student08 | C1     | student        |
      | student09 | C1     | student        |
      | student10 | C1     | student        |
      | student11 | C1     | student        |
    And the following "activities" exist:
      | activity | name              | intro            | course | idnumber |
      | journal  | Test journal name | Journal question | C1     | journal1 |

  Scenario: Teacher sees the users on several pages
    Given I am on the "journal1" "Activity" page logged in as "teacher1"
    And I follow "View 0 journal entries"
    And I set the field "Sort by" to "Lastname Ascending"
    When I set the field "Per page" to "10"
    Then I should see "Student 01"
    And I should see "Student 10"
    And I should not see "Student 11"
    And I click on "2" "link" in the ".pagination" "css_element"
    And I should see "Student 11"
    And I should see "Users who have not completed the journal"
    And I should not see "Student 01"
    And the field "Sort by" matches value "Lastname Ascending"
    And I set the field "Per page" to "All"
    And I should see "Student 01"
    And I should see "Student 11"
