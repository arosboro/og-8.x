<?php

/**
 * @file
 * Contains \Drupal\og_ui\Form\FieldSettingsForm.
 */

namespace Drupal\og_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\field\Field;
use Drupal\Core\Entity\EntityManagerInterface;

/**
 * Allow site admin to add or remove group fields from fieldable entities.
 */
class FieldSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'og_ui_field_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $options = array();
    foreach (\Drupal::service('entity.manager')->getDefinitions() as $entity_type => $entity_info) {
      if (empty($entity_info['fieldable'])) {
        continue;
      }

      foreach(entity_get_bundles($entity_type) as $bundle_name => $bundle) {
        // Prefix the bundle name with the entity type.
        $entity_name = check_plain("$entity_info[label] ($entity_type)");
        $options[$entity_name][$entity_type . ':' . $bundle_name] = filter_xss($bundle['label']);
      }
    }

    $form['bundle'] = array(
      '#title' => t('Bundles'),
      '#type' => 'select',
      '#options' => $options,
    );

    $options = array();
    foreach (og_fields_info() as $field_name => $field) {
      foreach ($field['type'] as $type) {
        $type_name = $type == 'group' ? t('Group') : t('Group content');
        $options[$type_name][$field_name] = filter_xss($field['instance']['label']);
      }
    }


    $selected_field_name = !empty($form_state['values']['field_type']) ? $form_state['values']['field_type'] : OG_AUDIENCE_FIELD;
    $selected_og_info = og_fields_info($selected_field_name);

    $form['field_info_wrapper'] = array(
      '#prefix' => '<div id="field-info-wrapper">',
      '#suffix' => '</div>',
      '#parents' => array('field_info_wrapper'),
      '#type' => 'fieldset',
    );

    $form['field_info_wrapper']['field_type'] = array(
      '#title' => t('Fields'),
      '#type' => 'select',
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $selected_field_name,
      '#ajax' => array(
        'callback' => '\Drupal\og_ui\Form\FieldSettingsForm::ajax_callback',
        'wrapper' => 'field-info-wrapper',
      ),
    );

    $form['field_info_wrapper']['description'] = array(
      '#markup' => $selected_og_info['description'],
    );

    if (!empty($selected_og_info['multiple'])) {
      $form['field_info_wrapper']['field_name'] = array(
        '#type' => 'textfield',
        '#title' => t('Field name'),
        '#description' => t('This field type supports adding multiple instances on the same bundle (i.e. the field name is not hardcoded).'),
        '#required' => TRUE,
        '#maxlength' => 32,
        '#default_value' => $selected_field_name,
      );
    }
    else {
      // Pass the field name as a value.
      $form['field_name_wrapper']['field_name'] = array(
        '#type' => 'value',
        '#value' => $selected_field_name,
      );
    }

    $field_enabled = array();
    $og_fields = og_fields_info();

    $og_fields_name = array_keys($og_fields);

    $entity_info = \Drupal::service('entity.manager')->getDefinitions();

    // Get the fields that exist in the bundle.
    foreach (Field::fieldInfo()->getFieldMap() as $entity_type => $fields) {
      foreach ($fields as $field_name => $info) {
        if (in_array($field_name, $og_fields_name) && !empty($info['bundles'])) {
          foreach ($info['bundles'] as $bundle_name) {
            $field_enabled[$entity_type][$bundle_name][] = $field_name;
          }
        }
      }
    }

    if ($field_enabled) {
      $form['group_fields'] = array(
        '#type' => 'vertical_tabs',
        '#weight' => 99,
      );

      // Show all the group fields of each bundle.
      foreach ($field_enabled as $entity_type => $bundles) {
        foreach ($bundles as $bundle => $fields) {
          $options = array();
          $bundles = entity_get_bundles($entity_type);
          $form['group_fields_' . $entity_type . '_' . $bundle] = array(
            '#type' => 'details',
            '#title' => t('@bundle - @entity entity', array('@bundle' => $bundles[$bundle]['label'], '@entity' => $entity_info[$entity_type]['label'])),
            '#collapsible' => TRUE,
            '#group' => 'group_fields',
          );
          foreach ($fields as $field_name) {
            $options[] = array(
              check_plain($og_fields[$field_name]['instance']['label']),
              filter_xss($og_fields[$field_name]['description']),
              l(t('Delete'), "admin/config/group/fields/$entity_type.$bundle.$field_name/delete"),
            );
          }

          $header = array(t('Field'), t('Description'), t('Operations'));
          $form['group_fields_' . $entity_type . '_' . $bundle]['fields'] = array(
            '#markup' => theme('table', array('header' => $header, 'rows' => $options)),
          );
        }
      }
    }
    else {
      $form['group_fields'] = array(
        '#markup' => t('There are no Group fields attached to any bundle yet.'),
      );
    }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Add field'),
    );

    return parent::buildForm($form, $form_state);
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
        $info = EntityManagerInterface->getDefinition($entity_type);
        $items[] = $info['label'];
      }
      \Drupal::formBuilder()->setErrorByName('bundles', t('Field %field can only be attached to %entities entity bundles.', $params + array('%entities' => implode(', ', $items))));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    list($entity_type, $bundle) = explode(':', $form_state['values']['bundle']);
    $field_name = $form_state['values']['field_name'];
    $field_type = $form_state['values']['field_type'];

    $og_field = og_fields_info($field_type);

    og_create_field($field_name, $entity_type, $bundle, $og_field);

    $params = array(
      '@field-type' => $og_field['instance']['label'],
      '@field-name' => $field_name,
      '@bundle' => $bundle,
    );

    if ($field_name == $field_type) {
      drupal_set_message(t('Added field @field-type to @bundle.', $params));
    }
    else {
      drupal_set_message(t('Added field @field-type (@field-name) to @bundle.', $params));
    }
  }

  public static function ajax_callback($form, &$form_state) {
    return $form['field_info_wrapper'];
  }
}
