<?php

/**
 * @file
 * Contains \Drupal\og_ui\Controller\OgUiController.
 */

namespace Drupal\og_ui\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Allow site admin to add or remove group fields from fieldable entities.
 */
class OgUiController extends ControllerBase {

  /**
   * Display an overview of group types with edit link.
   *
   * @param $type
   *   Either 'roles' or 'permissions'. Determines the edit link url.
   *
   * @return
   *   Renderable table of group types.
   */
  public function groupTypesOverview($type) {
    $header = array(t('Group type'), t('Operations'));
    $rows = array();

    foreach (og_get_all_group_bundle() as $entity_type => $bundles) {
      $entity_info = \Drupal::service('plugin.manager.entity')->getDefinition($entity_type);
      foreach ($bundles as $bundle_name => $bundle_label) {
        $row = array();
        $row[] = array('data' => check_plain($entity_info['label'] . ' - ' . $bundle_label));
        $row[] = array('data' => l(t('edit'), "admin/config/group/$type/$entity_type/$bundle_name"));

        $rows[] = $row;
      }
    }

    $build['roles_table'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('No group types available. Edit or <a href="@url">add a new content type</a> and set it to behave as a group.', array('@url' => url('admin/structure/types/add'))),
    );

    return $build;
  }

  /**
   * Title callback; Return the title for role or permission editing, based on
   * context.
   */
  public static function menuBundleRolesTitleCallback($group_type, $bundle, $rid = 0) {
    if ($rid) {
      // Get group type and bundle from role.
      $role = og_role_load($rid);
      if (!$role->group_type) {
        $title = str_replace('@type', '', $title);
        $title = str_replace('@bundle', t('Global'), $title);
      }

      $bundle = $role->group_bundle;
      $group_type = $role->group_type;

      $title = str_replace('@role', check_plain($role->name), $title);
    }

    $entity_info = \Drupal::service('plugin.manager.entity')->getDefinition($group_type);
    dpm($entity_info, 'entity_info');
    if (!empty($entity_info['label'])) {
      $title = str_replace('@type', check_plain($entity_info['label']), $title);
      $title = str_replace('@bundle', check_plain($entity_info['bundle_label']), $title);
    }

    return $title;
  }
}
