<?php

/**
 * @file
 * Contains \Drupal\og_ui\Form\AdminGlobalPermissionsForm.
 */

namespace Drupal\og_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\field\Field;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;

/**
 * Allow site admin to add or remove group fields from fieldable entities.
 */
class AdminGlobalPermissionsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'og_ui_admin_permissions';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    if (count(arg()) == '6') {
      $group_type = arg(4);
      $gid = 0;
      $bundle = arg(5);
      $rid = 0;
    }
    else if (count(arg()) == '7') {
      $group_type = arg(4);
      $gid = arg(5);
      $bundle = arg(6);
      $rid = 0;
    }
    else if (count(arg()) == '8') {
      $group_type = arg(4);
      $gid = arg(5);
      $bundle = arg(6);
      $rid = arg(7);
    }

    if ($rid) {
      // Get group type and bundle from role.
      $role = og_role_load($rid);
      $bundle = $role->group_bundle;
      $group_type = $role->group_type;
    }

    if ($gid) {
      og_set_breadcrumb($group_type, $gid, array(l(t('Group'), "$group_type/$gid/group")));
    }

    $form['group_type'] = array('#type' => 'value', '#value' => $group_type);
    $form['bundle'] = array('#type' => 'value', '#value' => $bundle);
    $form['gid'] = array('#type' => 'value', '#value' => $gid);

    $role_names = $this->_og_ui_get_role_names($group_type, $bundle, $gid, $rid);

    // Fetch permissions for all roles or the one selected role.
    $role_permissions = og_role_permissions($role_names);

    // Store $role_names for use when saving the data.
    $form['role_names'] = array(
      '#type' => 'value',
      '#value' => $role_names,
    );

    // Render role/permission overview:
    $options = array();
    $module_info = system_get_info('module');

    // Get a list of all the modules implementing a hook_permission() and sort by
    // display name.
    $permissions_by_module = array();
    foreach (og_get_permissions() as $perm => $value) {
      $module = $value['module'];
      $permissions_by_module[$module][$perm] = $value;
    }

    asort($permissions_by_module);

    foreach ($permissions_by_module as $module => $permissions) {
      $form['permission'][] = array(
        '#markup' => $module_info[$module]['name'],
        '#id' => $module,
      );

      foreach ($permissions as $perm => $perm_item) {
        // Fill in default values for the permission.
        $perm_item += array(
          'description' => '',
          'restrict access' => FALSE,
          'warning' => !empty($perm_item['restrict access']) ? t('Warning: Give to trusted roles only; this permission has security implications in the group context.') : '',
        );
        // If the user can manage permissions, but does not have administer
        // group permission, hide restricted permissions from them. This
        // prevents users from escalating their privileges.
        if ($gid && ($perm_item['restrict access'] && !og_user_access($group_type, $gid, 'administer group'))) {
          continue;
        }

        $options[$perm] = '';
        $form['permission'][$perm] = array(
          '#type' => 'item',
          '#markup' => $perm_item['title'],
          '#description' => theme('user_permission_description', array('permission_item' => $perm_item)),
        );
        foreach ($role_names as $rid => $name) {
          // Builds arrays for checked boxes for each role
          if (isset($role_permissions[$rid][$perm])) {
            $status[$rid][] = $perm;
          }
        }
      }
    }

    // Have to build checkboxes here after checkbox arrays are built
    foreach ($role_names as $rid => $name) {
      $form['checkboxes'][$rid] = array(
        '#type' => 'checkboxes',
        '#options' => $options,
        '#default_value' => isset($status[$rid]) ? $status[$rid] : array(),
        '#attributes' => array('class' => array('rid-' . $rid)),
      );
      $form['role_names'][$rid] = array('#markup' => check_plain($name), '#tree' => TRUE);
    }


    if (!$gid || !og_is_group_default_access($group_type, $gid)) {
      $form['actions'] = array('#type' => 'actions');
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Save permissions'),
      );
    }
    $form['#after_build'][] = 'og_ui_admin_permissions_after_build';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    list($entity_type, $bundle) = explode(':', $form_state['values']['bundle']);
    $field_name = $form_state['values']['field_name'];

    $og_field = og_fields_info($form_state['values']['field_type']);
    $bundles = entity_get_bundles($entity_type);
    $entity_info = \Drupal::service('entity.manager')->getDefinitions();

    $params = array(
      '%field' => $og_field['instance']['label'],
      '%bundle' => $bundles[$bundle]['label'],
      '%entity' => $entity_info['label'],
    );

    if (Field::fieldInfo()->getInstance($entity_type, $bundle, $field_name)) {
      \Drupal::formBuilder()->setErrorByName('bundles', t('Field %field already exists in %bundle.', $params));
    }

    // Check field can be attached to entity type.
    if (!empty($og_field['entity']) && !in_array($entity_type, $og_field['entity'])) {
      $items = array();
      foreach ($og_field['entity'] as $entity_type) {
        $info = \Drupal::service('plugin.manager.entity')->getDefinition($entity_type);
        $items[] = $info['label'];
      }
      \Drupal::formBuilder()->setErrorByName('bundles', t('Field %field can only be attached to %entities entity bundles.', $params + array('%entities' => implode(', ', $items))));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    foreach ($form_state['values']['role_names'] as $rid => $name) {
      og_role_change_permissions($rid, $form_state['values'][$rid]);
    }

    drupal_set_message(t('The changes have been saved.'));
  }

  /**
   * Helper function to get role names.
   *
   * @param $group_type
   *   Group entity type. E.g. 'node'.
   * @param $bundle
   *   Group bundle.
   * @param $gid
   *   Group item ID.
   * @param $rid
   *   Role ID.
   *
   * @return array
   *   Role names according to parameters.
   */
  public function _og_ui_get_role_names($group_type, $bundle, $gid, $rid) {
    if ($gid) {
      $group = entity_load($group_type, $gid);

      $bundle = $group->bundle();
      $gid = og_is_group_default_access($group_type, $group) ? 0 : $gid;
    }

    $role_names = og_roles($group_type, $bundle, $gid);
    if ($rid && !empty($role_names[$rid])) {
      $role_names = array($rid => $role_names[$rid]);
    }

    return $role_names;
  }
}
