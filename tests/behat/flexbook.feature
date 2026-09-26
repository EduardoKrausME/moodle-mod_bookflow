@mod @mod_flexbook
Feature: Create and read an interactive FlexBook
  In order to provide structured interactive study content
  As a teacher
  I need to create FlexBooks, chapters and content blocks

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | One | teacher1@example.com |
      | student1 | Student | One | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | FlexBook course | FLEX101 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | FLEX101 | editingteacher |
      | student1 | FLEX101 | student |

  Scenario: Teacher creates a FlexBook with automatic percentage completion
    Given I log in as "teacher1"
    And I am on "FlexBook course" course homepage with editing mode on
    When I add a "flexbook" activity to course "FlexBook course" section "1"
    And I set the following fields to these values:
      | FlexBook name | Safety handbook |
      | Minimum percentage | 80 |
    And I press "Save and display"
    Then I should see "Safety handbook"
    And I should see "0%"

  Scenario: Teacher creates chapters and content blocks
    Given the following "activities" exist:
      | activity | name | intro | course | idnumber |
      | flexbook | Safety handbook | Introduction | FLEX101 | flexbook1 |
    And I log in as "teacher1"
    And I am on the "Safety handbook" "flexbook activity" page
    When I follow "Manage chapters"
    And I set the field "Chapter title" to "Introduction"
    And I press "Save changes"
    Then I should see "Introduction"

  Scenario: Student starts with zero progress and can resume reading
    Given the following "activities" exist:
      | activity | name | intro | course | idnumber |
      | flexbook | Safety handbook | Introduction | FLEX101 | flexbook1 |
    And I log in as "student1"
    When I am on the "Safety handbook" "flexbook activity" page
    Then I should see "0%"
    And I should not see "You completed this FlexBook" in the ".flexbook-overview" "css_element"

  Scenario: Student uses private learning tools
    Given the following "activities" exist:
      | activity | name | intro | course | idnumber |
      | flexbook | Safety handbook | Introduction | FLEX101 | flexbook1 |
    And I log in as "student1"
    And I am on the "Safety handbook" "flexbook activity" page
    When I follow "My bookmarks"
    Then I should see "You have not bookmarked anything in this FlexBook"
    And I am on the "Safety handbook" "flexbook activity" page
    And I follow "My notes"
    Then I should see "You have not created notes in this FlexBook"

  Scenario: Teacher opens the progress report
    Given the following "activities" exist:
      | activity | name | intro | course | idnumber |
      | flexbook | Safety handbook | Introduction | FLEX101 | flexbook1 |
    And I log in as "teacher1"
    And I am on the "Safety handbook" "flexbook activity" page
    When I click on "Reports" "link" in the ".flexbook-tools" "css_element"
    Then I should see "Enrolled students"
    And I should see "Average progress"
