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
use Drupal\group\Entity\GroupContent;

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
  
  // Get the parent group IDs of an entity
  // https://drupal.stackexchange.com/questions/238755/how-to-get-group-ids-by-ids-of-elements-of-group-content
  public static function getGroupIdsByEntity($entity) {
    $group_ids = [];

    $group_contents = GroupContent::loadByEntity($entity);
    foreach ($group_contents as $group_content) {
      $group_ids[] = $group_content->getGroup()->id();
    }

    return $group_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Get the new group ID
    $new_group_id = $this->configuration['new_parent_group'];
    if($new_group_id == '_none') $new_group_id = NULL;

    // Get the old group ID. Assume the node can be in only one group.
    if( $entity == NULL ) {
      $old_group_id = NULL;
    }
    else {
      $groupIDs = self::getGroupIdsByEntity($entity);
      $old_group_id = (count($groupIDs) > 0) ? $groupIDs[0] : NULL;
    }

    if( $new_group_id == $old_group_id)
      return "The new group is the same as before.";

    if($old_group_id != NULL) {
      // If the old ID is NOT NULL, the new ID must be NULL.
      if($entity->hasField('field_group')) {
        $entity->field_group->entity = NULL;
      }
      
      $group_contents = \Drupal::entityTypeManager()
      ->getStorage('group_content')
      ->loadByProperties([ 
          'entity_id' => $entity->id(),
          'gid' => $old_group_id,
      ]);

      foreach ($group_contents as $item) {
        $item->delete();
      }
    }

    if($new_group_id != NULL)  {
      // Find all installed content types in the new group
      $new_group = \Drupal\group\Entity\Group::load($new_group_id);
      $new_group_title = $new_group->label();
      $installedContentTypes = $new_group->getGroupType()->getInstalledContentPlugins();

      $bundle = $entity->bundle();
      $bundle_installed_in_new_group = FALSE;
      foreach ($installedContentTypes as $pluginType) {
          $contentType = $pluginType->getPluginDefinition()['entity_bundle'];
          if($contentType == $bundle) {
            $bundle_installed_in_new_group = TRUE;
            break;
          }
      }

      if( !$bundle_installed_in_new_group )
        return "Content type $bundle not installed in new group $new_group_title.";

      // Switch group
      if($entity->hasField('field_group')) {
        $entity->field_group->entity = $new_group;
      }

      $entity->field_group->entity->addContent($entity, 'group_node:'.$entity->bundle());
    }

    return $this->t("Group updated.");
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