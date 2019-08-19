<?php

namespace Drupal\portland_migrations\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\media\Entity\Media;

/**
 * Called by the main policies migration for field_policy_type. Links existing
 * category taxonomy terms to field_policy_catetory.
 * 
 * NOTE: the policies_categories migration must be run before policies. 
 *
 * @MigrateProcessPlugin(
 *   id = "link_policy_category"
 * )
 */
class LinkPolicyCategory extends ProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // $array = preg_split("/-/", $value);
    // $policy_type_code = $array[0];

    // if (!isset($GLOBALS['policy_types'])) {
    //   // store array of policy_types in global
    //   $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('policy_type');
    //   foreach ($terms as $term) {
    //     $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term->tid);
    //     $type_code = $term_obj->get('field_type_code')->value;
    //     $GLOBALS['policy_types'][$type_code] = $term_obj->get('tid')->value;
    //   }
    // }

    // return $GLOBALS['policy_types'][$policy_type_code];

    return $value;
  }

}