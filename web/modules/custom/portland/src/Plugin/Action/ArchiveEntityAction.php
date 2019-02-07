<?php

namespace Drupal\portland\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Archive an entity.
 *
 * @Action(
 *   id = "portland_archive_entity",
 *   label = @Translation("Archive selected content"),
 *   type = "",
 *   confirm = TRUE,
 * )
 */
class ArchiveEntityAction extends ViewsBulkOperationsActionBase {

  use DependencyTrait;

  /**
   * The moderation info service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Moderation state change constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModerationInformationInterface $moderation_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    if (!empty($this->configuration['workflow'])) {
      $this->addDependency('config', 'workflows.workflow.' . $this->configuration['workflow']);
    }
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    $entity = $this->loadLatestRevision($entity);
    // Machine names: published, draft, review, archived
    $entity->moderation_state->value = 'archived';
    $entity->save();
  }

  /**
   * Loads the latest revision of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The latest revision of content entity.
   */
  protected function loadLatestRevision(ContentEntityInterface $entity) {
    return $this->moderationInfo->getLatestRevision($entity->getEntityTypeId(), $entity->id());
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object || !$object instanceof ContentEntityInterface) {
      $result = AccessResult::forbidden('Not a valid entity.');
      return $return_as_object ? $result : $result->isAllowed();
    }
    if ($workflow = $this->moderationInfo->getWorkflowForEntity($object)) {
      if ($workflow->id() !== $this->configuration['workflow']) {
        $result = AccessResult::forbidden('Not a valid workflow for this entity.');
        $result->addCacheableDependency($workflow);
        return $return_as_object ? $result : $result->isAllowed();
      }
    }
    else {
      $result = AccessResult::forbidden('No workflow found for the entity.');
      return $return_as_object ? $result : $result->isAllowed();
    }
    $object = $this->loadLatestRevision($object);
    // Let content moderation do its job. See content_moderation_entity_access()
    // for more details.
    $access = $object->access('update', $account, TRUE);

    $to_state_id = $this->configuration['state'];
    $from_state = $workflow->getTypePlugin()->getState($object->moderation_state->value);
    // Make sure we can make the transition.
    if ($from_state->canTransitionTo($to_state_id)) {
      $transition = $from_state->getTransitionTo($to_state_id);
      $result = AccessResult::allowedIfHasPermission($account, 'use ' . $workflow->id() . ' transition ' . $transition->id())
        ->andIf($access);
    }
    else {
      $result = AccessResult::forbidden('No valid transition found.');
    }
    $result->addCacheableDependency($workflow);
    return $return_as_object ? $result : $result->isAllowed();
  }

}
