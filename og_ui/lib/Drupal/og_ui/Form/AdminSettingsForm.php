<?php

/**
 * @file
 * Contains \Drupal\og_ui\Form\AdminSettingsForm.
 */

namespace Drupal\og_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\views\Views;

/**
 * Provides an administration settings form.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'og_ui_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    // Get all settings
    $og_config = $this->configFactory->get('og.settings');
    $og_settings = $og_config->get();
    $og_ui_config = $this->configFactory->get('og_ui.settings');
    $og_ui_settings = $og_ui_config->get();

    $form['og_group_manager_full_access'] = array(
      '#type' => 'checkbox',
      '#title' => t('Group manager full permissions'),
      '#description' => t('When enabled the group manager will have all the permissions in the group.'),
      '#default_value' => $og_settings['group_manager_full_access'],
    );

    $form['og_node_access_strict'] = array(
      '#type' => 'checkbox',
      '#title' => t('Strict node access permissions'),
      '#description' => t('When enabled Organic groups will restrict permissions for creating, updating and deleting according to the  Organic groups access settings. Example: A content editor with the <em>Edit any page content</em> permission who is not a member of a group would be denied access to modifying page content in that group. (For restricting view access use the Organic groups access control module.)'),
      '#default_value' => $og_settings['node_access_strict'],
    );

    $form['og_ui_admin_people_view'] = array(
      '#type' => 'select',
      '#title' => t('Admin people View'),
      '#description' => t('Select the View that should be used to show and control the people in the group.'),
      '#options' => Views::getViewsAsOptions(),
      '#default_value' => $og_ui_settings['admin_people_view'],
      '#required' => TRUE,
    );
    if ($group_bundles = og_get_all_group_bundle()) {
      $form['og_group_manager_rids'] = array(
        '#type' => 'fieldset',
        '#title' => t('Group manager default roles'),
        '#description' => t('Select the role(s) a group manager will be granted upon creating a new group.'),
      );
      /*
      // TODO add group manager roles
      // Add group manager default roles.
      $entity_info = Drupal::service('entity.manager')->getDefinitions();
      foreach ($group_bundles as $entity_type => $bundles) {
        foreach ($bundles as $bundle_name => $bundle_label) {
          $og_roles = og_roles($entity_type, $bundle_name, 0, FALSE, FALSE);
          if (!$og_roles) {
            continue;
          }

          $params = array(
            '@entity-label' => $entity_info[$entity_type]['label'],
            '@bundle-label' => $bundle_label,
          );

          $name = 'og_group_manager_default_rids_' . $entity_type . '_' . $bundle_name;
          $form['og_group_manager_rids'][$name] = array(
            '#type' => 'select',
            '#title' => t('Roles in @entity-label - @bundle-label', $params),
            '#options' => $og_roles,
            '#multiple' => TRUE,
            '#default_value' => variable_get($name, array()),
          );
        }
      }
      */
    }

    // TODO Removed as Features are no longer required in Drupal 8
    /*
    $form['og_features_ignore_og_fields'] = array(
      '#type' => 'checkbox',
      '#title' => t('Prevent "Features" export piping'),
      '#description' => t('When exporting using Features module a content-type, this will prevent from OG related fields to be exported.'),
      '#default_value' => variable_get('og_features_ignore_og_fields', FALSE),
      '#access' => module_exists('features'),
    );
    */

    $form['og_use_queue'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use queue'),
      '#description' => t("Use the core's queue process to operations such as deleting memberships when groups are deleted."),
      '#default_value' => $og_settings['use_queue'],
    );

    $form['og_orphans_delete'] = array(
      '#type' => 'checkbox',
      '#title' => t('Delete orphans'),
      '#description' => t('Delete "Orphan" group-content (not including users), when the group is deleted.'),
      '#default_value' => $og_settings['orphans_delete'],
      '#states' => array(
        'visible' => array(
          ':input[name="og_use_queue"]' => array('checked' => TRUE),
        ),
      ),
      '#attributes' => array(
        'class' => array('entity_reference-settings'),
      ),
    );

    // Re-use Entity-reference CSS for indentation.
    $form['#attached']['css'][] = drupal_get_path('module', 'entity_reference') . '/entity_reference.admin.css';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {

    // Get config factory
    $config = $this->configFactory->get('bbb.settings');

    $form_values = $form_state['values'];

    $config
        ->set('security_salt', $form_values['bbb_server']['security_salt'])
        ->set('base_url', $form_values['bbb_server']['base_url'])
        ->set('display_mode', $form_values['bbb_client']['display_mode'])
        ->set('display_height', $form_values['bbb_client']['display_height'])
        ->set('display_width', $form_values['bbb_client']['display_width'])
        ->save();

    parent::submitForm($form, $form_state);
  }
}
