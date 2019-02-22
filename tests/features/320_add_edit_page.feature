@api @javascript @multidev @dev
Feature: Only members can create content of type Page
  In order to manage group content on the site
  As a group member
  I need to be able to create and save content of type Page

  Scenario: Add a page of type Page

  Scenario: Edit a page of type Page

    Given I am logged in as user "marty.member@portlandoregon.gov"
    When I visit "/powr"
    Then I should see "+ Add Content"

    When I click "+ Add Content"
    Then I should see "Group node (Page)"

    When I click "Group node (Page)"
    Then I should see "Create Project: Group node (Page)"

    When I fill in "edit-title-0-value" with "Test page"
    And I select "Information" from "edit-field-page-type"
    And I select "Biking" from "edit-field-topics"
    And I select "Visitors" from "edit-field-audience"
    And I fill in "edit-field-summary-0-value" with "This is the summary field"
    And I fill in wysiwyg on field "edit-field-body-content-0-value" with "This is the body field"
    # Related content field
    And I fill in "edit-field-legacy-path-0-value" with "/legacy/path/1234"
    And I fill in "edit-revision-log-0-value" with "Test revision message"
    And I press "Save"
    And I wait for 1 second
    Then I should see "Page Test page has been created"
    And I should see "Information"
    And I should see "This is the summary field"
    And I should see "This is the body field"
