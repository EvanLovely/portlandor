<?php
/**
 * @file
 * Contains \portland\Plugin\Block\SearchBlock
 */

namespace Drupal\portland\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;

/**
 * Provides a 'portland revision' block.
 *
 * @Block(
 *   id = "portland_revision_block",
 *   admin_label = @Translation("Portland Revision Block"),
 *
 * )
 */
class RevisionBlock extends BlockBase {
    private static $severity_icon = [
      'danger' => 'ban',
      'warning' => 'exclamation-circle',
      'success' => 'info-circle'
    ];

    /**
     * {@inheritdoc}
     */
    public function getCacheMaxAge() {
      return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
      $current_path = \Drupal::service('path.current')->getPath();
      $uri_parts = explode('/', $current_path);
      $count = count($uri_parts);

      $nid = NULL;
      $node_default_revision= NULL;
      $node_latest_revision = NULL;
      $node_current_revision = NULL;

      // Viewing the latest URL, which is only valid when the latest revision is draft or review. " /node/221/latest"
      if($count == 4 && $uri_parts[1] == "node" && $uri_parts[3] == "latest") {
        $nid = $uri_parts[2];
        $node_latest_revision = self::loadLatestRevision($nid);        $node_default_revision= \Drupal::entityTypeManager()->getStorage('node')->load($nid);

        return self::buildRenderArray(
          $node_latest_revision, 
          $node_latest_revision, // Curent revision is Latest revision
          $node_default_revision
        );
      }

      // Viewing the default revision of the node. "/node/221"
      else if($count == 3 && $uri_parts[1] == "node") {
        $nid = $uri_parts[2];
        $node_latest_revision = self::loadLatestRevision($nid);
        $node_default_revision= \Drupal::entityTypeManager()->getStorage('node')->load($nid);

        return self::buildRenderArray(
          $node_latest_revision, 
          $node_default_revision, // Curent revision is default revision
          $node_default_revision
        );
      }
      // Viewing the revision. "/node/221/revisions/3669/view"
      else if ($count == 6 && $uri_parts[3] == "revisions" && $uri_parts[5] == "view") {
        $nid = $uri_parts[2];
        $vid = $uri_parts[4];
        $node_latest_revision = self::loadLatestRevision($nid);
        $node_current_revision= \Drupal::entityTypeManager()->getStorage('node')->loadRevision($vid);
        $node_default_revision= \Drupal::entityTypeManager()->getStorage('node')->load($nid);

        // Curent revision is default revision
        return self::buildRenderArray(
          $node_latest_revision, 
          $node_current_revision, 
          $node_default_revision
        );
      }

      return array(
        '#theme' => 'portland_revision_block',
      );
    }

    public static function buildRenderArray($node_latest_revision, $node_current_revision, $node_default_revision) {
      $nid = $node_current_revision->nid->value;
      $node_latest_vid = $node_latest_revision->vid->value;
      $node_latest_status = $node_latest_revision->status->value;
      $node_current_vid = $node_current_revision->vid->value;
      $node_current_status = $node_current_revision->status->value;
      $node_current_is_archived = ($node_current_revision->moderation_state->value == 'archived');
      $node_default_vid = $node_default_revision->vid->value;
      $node_default_status = $node_default_revision->status->value;

      // Set the default
      $render_array = [
        '#theme' => 'portland_revision_block',
        '#alert_color' => self::getSeverity($node_current_revision),
        '#alert_icon' => self::$severity_icon[self::getSeverity($node_current_revision)],
        '#current_revision_state' => self::getModerationStateInString($node_current_revision),
        '#latest_revision_state' => [
          '#markup' => ' and new <strong>'. self::getModerationStateInString($node_latest_revision) . '</strong> revision exists.'],
        '#revision_link' => "/node/$nid/revisions/$node_latest_vid/view",
        '#revision_link_text' => 'View latest version',
      ];
      /*
      latest == current > default
      default is published
        (current is archived) ? DANGER : WARN, current state, NULL, view published
      default is archived
        AS_IS, current state, NULL, NULL
      */
      if($node_latest_vid == $node_current_vid && $node_current_vid > $node_default_vid) {
        if($node_default_status == 1) {
          $render_array['#alert_color'] = ($node_current_is_archived) ? 'danger' : 'warning';
          $render_array['#alert_icon'] = ($node_current_is_archived) ? 'ban' : 'exclamation-circle';
          $render_array['#latest_revision_state'] = NULL;
          $render_array['#revision_link'] = "/node/$nid";
          $render_array['#revision_link_text'] = "View published version";
        }
        else {
          $render_array['#latest_revision_state'] = NULL;
          $render_array['#revision_link'] = NULL;
          $render_array['#revision_link_text'] = NULL;
        }
      }
      /*
      latest > current >= default
        (current is archived) ? DANGER : WARN, current state, latest state, view latest
      */
      else if($node_latest_vid > $node_current_vid && $node_current_vid >= $node_default_vid) {
        $render_array['#alert_color'] = ($node_current_is_archived) ? 'danger' : 'warning';
        $render_array['#alert_icon'] = ($node_current_is_archived) ? 'ban' : 'exclamation-circle';
      }
      /*
      latest = current = default
        default is published
          INFO, current state
        default is archived
          DANGER, current state
      */
      else if($node_latest_vid == $node_current_vid && $node_current_vid == $node_default_vid) {
        $render_array['#latest_revision_state'] = NULL;
        $render_array['#revision_link'] = NULL;
        $render_array['#revision_link_text'] = NULL;      
      }
      /*
      latest = default > current
        default is published
          (current is archived) ? DANGER : WARN, current state, latest state, view published
        default is archived
          (current is archived) ? DANGER : WARN, current state, latest state, view latest
      */
      else if($node_latest_vid == $node_default_vid && $node_default_vid > $node_current_vid ) {
        $render_array['#alert_color'] = ($node_current_is_archived) ? 'danger' : 'warning';
        $render_array['#alert_icon'] = ($node_current_is_archived) ? 'ban' : 'exclamation-circle';
        if($node_default_status == 1) {
          $render_array['#revision_link'] = "/node/$nid";
          $render_array['#revision_link_text'] = "View published version";
        }
      }
      /*
      latest > default > current
        (current is archived) ? DANGER : WARN, current state, latest state, view latest
      */
      else if($node_latest_vid > $node_default_vid && $node_default_vid > $node_current_vid ) {
        $render_array['#alert_color'] = ($node_current_is_archived) ? 'danger' : 'warning';
        $render_array['#alert_icon'] = ($node_current_is_archived) ? 'ban' : 'exclamation-circle';
      }

      return $render_array;
    }

    public static function getSeverity($node) {
      if($node->status->value == 0) {
        return ($node->moderation_state->value == 'archived') ? 'danger' : 'warning';
      }
      return 'success';
    }

    public static function getModerationStateInString($node) {
      $published = $node->status->value;
      $moderation_state = $node->moderation_state->value;
      if($published == "0") {
        switch($moderation_state) {
          case "archived":
            $result = "Unpublished/archived";
            break;
          case "review":
            $result = "In review";
            break;
          default:
            $result = "Draft";
        }
      }
      else {
        $result = "Published";
      }
      return $result;
    }

    public static function loadLatestRevision($nid) {
      // Load the latest revision
      $latestNode = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
                  ->latestRevision()
                  ->condition('nid', $nid)
                  ->execute();
      reset($latestNode);
      $latestRevisionId = key($latestNode);
      return \Drupal::entityTypeManager()->getStorage('node')->loadRevision($latestRevisionId);
    }
  }