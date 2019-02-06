<?php

namespace Drupal\portland\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\group\GroupMembershipLoaderInterface;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\group\Entity\GroupContentTypeInterface;

/**
 * Change group for multiple content nodes.
 *
 * @Action(
 *   id = "portland_change_group",
 *   label = @Translation("Change group"),
 *   type = "",
 *   confirm = TRUE,
 * )
 */

class ChangeGroupAction extends ViewsBulkOperationsActionBase implements PluginFormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $groupTypes = ['bureau_office', 'elected_official', 'projects', 'programs'];
    $options = ['_none' => '- None -'];
    foreach ($groupTypes as $groupType) {
        // Get all groups of this type. E.g. bureau_office
        $grps = \Drupal::entityTypeManager()
            ->getStorage('group')
            ->loadByProperties(['type' => $groupType]);
        foreach($grps as $group) {
            // Get group ID and label
            $options[$group->id->value] = $group->label->value;
        }
    }
    aSort($options); // Sort by group title/label

    // $group_memberships = \Drupal::service('group.membership_loader')->loadByUser();
    // $options = ['_none' => '- None -'];
    // foreach( $group_memberships as $group_membership ) {
    //     $group = $group_membership->getGroup();
    //     $options[$group->id->value] = $group->label->value;
    // }

    $form['new_parent_group'] = [
      '#title' => t('Select the new parent group:'),
      '#type' => 'select',
      '#options' => $options,
      '#required' => TRUE,
      // '#default_value' => '_none',
    ];
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Get the new group ID
    $new_group_id = $this->configuration['new_parent_group'];

    // Remove from the group
    if($new_group_id == '_none') {

    }
    else {
      // Find all installed content types in the new group
      $group = $group = \Drupal\group\Entity\Group::load($new_group_id);
      $installedContentTypes = $group->getGroupType()->getInstalledContentPlugins();

      $bundle = $entity->bundle();
      $bundle_installed_in_new_group = FALSE;
      foreach ($installedContentTypes as $pluginType) {
          $contentType = $pluginType->getPluginDefinition()['entity_bundle'];
          if($contentType == $bundle) {
            $bundle_installed_in_new_group = TRUE;
            break;
          }
      }

      if( !$bundle_installed_in_new_group ) return "Content type $bundle not installed in new group.";

      // Check if this node already belongs to the group
  
      // Switch group
    }

    // Don't return anything for a default completion message, otherwise return translatable markup.
    return $this->t('Group changed');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'node') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }

    // Other entity types may have different
    // access methods and properties.
    return TRUE;
  }

}