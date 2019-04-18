@api @multidev @dev
Feature: Members can manage group content
  In order to manage content in my group
  As a group member
  I need to be able to create and edit group content nodes

  Scenario: Add service content
    Given I am logged in as user "Marty Member"
    When I visit "/test"
    Then I should see "+ Add Content"

    When I click "+ Add Content"
    Then I should see "Group Event"
    And I should see "Group News"
    And I should see "Group Page"
    And I should see "Group Notification"
    And I should see "Group Service"

    When I click "Service"
    And I should see "Title"
    And I should see "Step title"
    And I should see "Step instruction"
    And I should see "Related content"
    And I should see "Legacy path"

    When I fill in "edit-title-0-value" with "Test service"
    And I select "Online" from "edit-field-service-mode-0-subform-field-service-modes"
    And I fill in "edit-revision-log-0-value" with:
      """
      Test revision message
      """
    And I select "published" from "moderation_state[0][state]"
    And I press "Save"
    Then I should see "Service Test service has been created."

  Scenario: Edit and delete service
    Given I am logged in as user "Marty Member"
    And I wait for "10000"
    When I visit "/test/services/test-service"
    And I click "Edit" in the "tabs" region
    Then I should see "Title"

    When I click "Delete" in the "tabs" region
    Then I should see "This action cannot be undone."

    When I press "Delete"
    Then I should see "The Service Test service has been deleted."
