@mod @mod_journal
Feature: Teacher can view, comment and grade students entries
  In order to interact with students to refine an answer
  As a teacher
  I need to comment and grade users entries

  Scenario: Teacher grades and adds/edits feedback to student's entries
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@asd.com |
      | student1 | Student | 1 | student1@asd.com |
      | student2 | Student | 2 | student2@asd.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And the following "activities" exist:
      | activity | name               | intro            | course | idnumber |
      | journal  | Test journal name  | Journal question | C1     | journal1 |

    # Two student journal entries.
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test journal name"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | Student 1 first reply |
    And I press "Save changes"
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Test journal name"
    And I press "Start or edit my journal entry"
    And I set the following fields to these values:
      | Entry | Student 2 first reply |
    And I press "Save changes"
    And I log out

    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test journal name"
    And I follow "View 2 journal entries"
    Then I should see "Student 1 first reply" in the "//table[@class='journaluserentry']/descendant::td[@class='userfullname'][contains(., 'Student 1')]/ancestor::table[@class='journaluserentry']" "xpath_element"
    And I should see "Student 2 first reply" in the "//table[@class='journaluserentry']/descendant::td[@class='userfullname'][contains(., 'Student 2')]/ancestor::table[@class='journaluserentry']" "xpath_element"

    # Given I set the field with xpath "" to "" was not backported to 2.7...
    # We should change this in 2.8 onwards.
    And I set the field "r2" to "94"
    And I set the field "c2" to "Well done macho man"
    And I set the field "r1" to "22"
    And I set the field "c1" to "You can do it better"
    And I press "Save all my feedback"
    And I should see "Feedback updated for 2 entries"
    And the field "r2" matches value "94"
    And the field "c2" matches value "Well done macho man"
    And the field "r1" matches value "22"
    And the field "c1" matches value "You can do it better"
    And I set the field "c1" to "You could not do it better"
    And I set the field "r1" to "100"
    And I press "Save all my feedback"
    And I should see "Feedback updated for 1 entries"
    And the field "c1" matches value "You could not do it better"
    And the field "r1" matches value "100"
    And the field "c2" matches value "Well done macho man"
