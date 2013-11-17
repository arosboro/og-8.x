<?php

/**
 * @file
 * Contains \Drupal\og\Plugin\Field\FieldWidget\OgComplexWidget.
 */

namespace Drupal\og\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\Field;
use Drupal\entity_reference\Plugin\Field\FieldWidget\AutocompleteWidgetBase;
use Drupal\entity_reference\Plugin\Type\SelectionPluginManager;

/**
 * Plugin implementation of the 'entity_reference autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "og_complex",
 *   label = @Translation("OG Reference"),
 *   description = @Translation("Complex widget to reference groups."),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   settings = {
 *     "match_operator" = "CONTAINS",
 *     "size" = 60,
 *     "autocomplete_type" = "single",
 *     "placeholder" = ""
 *   },
 *   multiple_values = FALSE
 * )
 */
class OgComplexWidget extends AutocompleteWidgetBase {
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, array &$form_state) {
    $field_name = $this->fieldDefinition->getFieldName();
    $cardinality = $this->fieldDefinition->getFieldCardinality();
    $parents = $form['#parents'];

    // Determine the number of widgets to display.
    switch ($cardinality) {
      case FieldDefinitionInterface::CARDINALITY_UNLIMITED:
        $field_state = field_form_get_state($parents, $field_name, $form_state);
        $max = $field_state['items_count'];
        $is_multiple = TRUE;
        break;

      default:
        $max = $cardinality - 1;
        $is_multiple = ($cardinality > 1);
        break;
    }

    $id_prefix = implode('-', array_merge($parents, array($field_name)));
    $wrapper_id = drupal_html_id($id_prefix . '-add-more-wrapper');

    $title = check_plain($this->fieldDefinition->getFieldLabel());
    $description = field_filter_xss(\Drupal::token()->replace($this->fieldDefinition->getFieldDescription()));

    $elements = array();

    for ($delta = 0; $delta <= $max; $delta++) {
      // For multiple fields, title and description are handled by the wrapping
      // table.
      $element = $form_state['complex_element']['#value'];
      $widget = $form_state['widget']['#value'];
      $element = $widget->formSingleElement($items, $delta, $element, $form, $form_state);

      if ($element) {
        // Input field for the delta (drag-n-drop reordering).
        if ($is_multiple) {
          // We name the element '_weight' to avoid clashing with elements
          // defined by widget.
          $element['_weight'] = array(
            '#type' => 'weight',
            '#title' => t('Weight for row @number', array('@number' => $delta + 1)),
            '#title_display' => 'invisible',

            // Note: this 'delta' is the FAPI #type 'weight' element's property.
            '#delta' => $max,
            '#default_value' => $items[$delta]->_weight ? : $delta,
            '#weight' => 100,
          );
        }

        $elements[$delta] = $element;
      }
    }

    if ($elements) {
      $elements += array(
        '#theme' => 'field_multiple_value_form',
        '#field_name' => $field_name,
        '#cardinality' => $cardinality,
        '#cardinality_multiple' => $this->fieldDefinition->isFieldMultiple(),
        '#required' => $this->fieldDefinition->isFieldRequired(),
        '#title' => $title,
        '#description' => $description,
        '#prefix' => '<div id="' . $wrapper_id . '">',
        '#suffix' => '</div>',
        '#max_delta' => $max,
      );

      // Add 'add more' button, if not working with a programmed form.
      if ($cardinality == FieldDefinitionInterface::CARDINALITY_UNLIMITED && empty($form_state['programmed'])) {
        $elements['add_more'] = array(
          '#type' => 'submit',
          '#name' => strtr($id_prefix, '-', '_') . '_add_more',
          '#value' => t('Add another item'),
          '#attributes' => array('class' => array('field-add-more-submit')),
          '#limit_validation_errors' => array(array_merge($parents, array($field_name))),
          '#submit' => array('field_add_more_submit'),
          '#ajax' => array(
            'callback' => 'field_add_more_js',
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
          ),
        );
      }
    }

    return $elements;
  }

  public function form(FieldItemListInterface $items, array &$form, array &$form_state, $get_delta = NULL) {
    //    $form_state['programmed'] = TRUE;

    // We let the Field API handles multiple values for us, only take care of
    // the one matching our delta.
    $delta = isset($get_delta) ? $get_delta : 0;
    if (isset($items[$delta])) {
      $items->setValue(array($items[$delta]->getValue()));
    }
    else {
      $items->setValue(array());
    }
    $entity_type = $items->getEntity()->entityType();
    $entity = $items->getEntity();

    if (!$entity) {
      return;
    }

    $field = $items->getFieldDefinition();
    $field_name = $field->getFieldName();
    $settings = $field->getFieldSettings();
    $cardinality = $field->getFieldCardinality();
    $parents = $form['#parents'];

    if ($settings['handler'] != 'og' && strpos($settings['handler'], 'og_') !== 0) {
      $params = array('%label' => $field->label);
      \Drupal::formBuilder()->setError($form, t('Field %label is a group-audience but its Entity selection mode is not defined as "Organic groups" in the field settings page.', $params));
      return parent::form($items, $form, $form_state, $get_delta);
    }

    // Cache the processed entity, to make sure we call the widget only once.
    $cache = &drupal_static(__FUNCTION__, array());
    $id = $entity->id();
    $bundle = $entity->bundle();
    $field_name = $field->getFieldName();

    $identifier = $field_name . ':' . $entity_type . ':' . $bundle . ':' . $id;
    if (isset($cache[$identifier])) {
      // TODO remove comment.
      //return array();
    }
    $cache[$identifier] = TRUE;

//    ctools_include('fields');

    $field_modes = array('default');
    $has_admin = FALSE;

    // The group IDs that might not be accessible by the user, but we need
    // to keep even after saving.
    $element['#other_groups_ids'] = array();
    $element['#element_validate'][] = 'og_complex_widget_element_validate';

    global $user;
    if ($user->hasPermission('administer group')) {
      $has_admin = TRUE;
      $field_modes[] = 'admin';
    }

    // Build an array of entity IDs. Field's $items are loaded
    // in OgBehaviorHandler::load().
    $entity_gids = array();
    foreach ($items as $item) {
      $entity_gids[] = $item->target_id;
    }

    $target_type = $field->getFieldSetting('target_type');

    $user_gids = og_get_entity_groups();
    $user_gids = !empty($user_gids[$target_type]) ? $user_gids[$target_type] : array();

    // Get the "Other group" group IDs.
    $other_groups_ids = array_diff($entity_gids, $user_gids);

    // Collect widget elements.
    $elements = array();
    $complex_element = $element;
    foreach ($field_modes as $field_mode) {
      $field = clone $field;
      $mocked_instance = \Drupal::config("field.instance.$entity_type.$bundle.$field_name")->get();
      $dummy_entity = $entity->createDuplicate();

      if ($has_admin) {
        $complex_element['#required'] = FALSE;
        if ($field_mode == 'default') {
          $complex_element['#title'] = t('Your groups');
          if ($entity_type == 'user') {
            $complex_element['#description']= t('Associate this user with groups you belong to.');
          }
          else {
            $complex_element['#description'] = t('Associate this content with groups you belong to.');
          }
        }
        else {
          $complex_element['#title'] = t('Other groups');
          if ($entity_type == 'user') {
            $complex_element['#description'] = t('As groups administrator, associate this user with groups you do <em>not</em> belong to.');
          }
          else {
            $complex_element['#description'] = t('As groups administrator, associate this content with groups you do <em>not</em> belong to.');
          }
        }

        if ($id) {
          // The field might be required, and it will throw an exception
          // when we try to set an empty value, so change the wrapper's
          // info.
//          $wrapper = entity_metadata_wrapper($entity_type, $dummy_entity, array('property info alter' => 'og_property_info_alter', 'field name' => $field_name));
          if ($field_mode == 'admin') {
            // Keep only the hidden group IDs on the entity, so they won't
            // appear again on the "admin" field, for example on an autocomplete
            // widget type.
            $valid_ids = $other_groups_ids ? \Drupal::service('plugin.manager.entity_reference.selection')->getSelectionHandler($field)->validateReferenceableEntities($other_groups_ids) : array();
            $valid_ids = $cardinality  == 1 ? reset($valid_ids) : $valid_ids;
            $dummy_entity->{$field_name}->setValue($valid_ids ? $valid_ids : NULL);
          }
          else {
            // Keep only the groups that belong to the user and to the entity.
            $my_group_ids = array_values(array_intersect($user_gids, $entity_gids));
            $valid_ids = $my_group_ids ? \Drupal::service('plugin.manager.entity_reference.selection')->getSelectionHandler($field)->validateReferenceableEntities($my_group_ids) : array();
            $valid_ids = $cardinality == 1 ? reset($valid_ids) : $valid_ids;
            $dummy_entity->{$field_name}->setValue($valid_ids ? $valid_ids : NULL);
          }
        }
      }
      else {
        // Non-admin user.
        $mocked_instance_other_groups = $mocked_instance;
        $mocked_instance_other_groups['field_mode'] = 'admin';
        if ($other_groups_ids && $valid_ids = SelectionPluginManager::getSelectionHandler($field)->validateReferencableEntities($other_groups_ids)) {
          foreach ($valid_ids as $id) {
            $complex_element['#other_groups_ids'][] = array('target_id' => $id);
          }
        }
      }

      $dummy_form_state = $form_state;
      if (empty($form_state['rebuild'])) {
        // Store field information in $form_state.
        if (!field_form_get_state($parents, $field_name, $form_state)) {
          $field_state = array(
            'items_count' => count($items),
            'array_parents' => array(),
            'constraint_violations' => array(),
          );
          field_form_set_state($parents, $field_name, $dummy_form_state, $field_state);
        }

        // Form is "fresh" (i.e. not call from field_add_more_submit()), so
        // re-set the items-count, to show the correct amount for the mocked
        // instance.
        $dummy_form_state['field']['#parents']['#fields'][$field_name]['items_count'] =  count($field->get('target_id'));
      }

//      $new_element = ctools_field_invoke_field($mocked_instance, 'form', $entity_type, $dummy_entity, $form, $dummy_form_state, array('default' => TRUE));
      $widget_type = $mocked_instance['settings']['behaviors']['og_widget'][$field_mode]['widget_type'];
      $widget = og_get_mocked_widget($widget_type, $field);

      // If the widget is handling multiple values (e.g Options), or if we are
      // displaying an individual element, just get a single form element and make
      // it the $delta value.
      $definition = parent::getPluginDefinition();
      $items = $dummy_entity->{$field_name};
      if (isset($get_delta) || $definition['multiple_values']) {
        $delta = isset($get_delta) ? $get_delta : 0;
        $new_element = $widget->formSingleElement($items, $delta, $complex_element, $form, $dummy_form_state);
//        $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);
      }
      // If the widget does not handle multiple values itself, (and we are not
      // displaying an individual element), process the multiple value form.
      else if (in_array($widget_type, array('entity_reference_autocomplete', 'entity_reference_autocomplete_tags'))) {
        $dummy_form_state['complex_element'] = array(
          '#type' => 'value',
          '#value' => $complex_element,
        );
        $dummy_form_state['widget'] = array(
          '#type' => 'value',
          '#value' => $widget,
        );
        $new_element = $this->formMultipleElements($items, $form, $dummy_form_state);
        $new_element['#title'] = '';
      }
      else {
        $delta = isset($get_delta) ? $get_delta : 0;
        $new_element = $widget->formSingleElement($items, $delta, $complex_element, $form, $dummy_form_state);
//        $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);
      }

      if ($new_element) {
        if (isset($get_delta)) {
          // If we are processing a specific delta value for a field where the
          // field module handles multiples, set the delta in the result.
          $elements[$field_mode][$delta] = $new_element;
        }
        else {
          // For fields that handle their own processing, we cannot make
          // assumptions about how the field is structured, just merge in the
          // returned element.
          $elements[$field_mode] = $new_element;
        }
      }

      if (in_array($widget_type, array('entity_reference_autocomplete', 'entity_reference_autocomplete_tags'))) {

        // Change the "Add more" button name so it adds only the needed
        // element.
        if (!empty($elements[$field_mode]['add_more']['#name'])) {
          $elements[$field_mode]['add_more']['#name'] .= '__' . $field_mode;
        }

        /*if ($widget_type == 'entity_reference_autocomplete') {
          foreach (array_keys($elements[$field_mode]) as $delta) {
            if (!is_numeric($delta)) {
              continue;
            }

            $sub_element = &$elements[$field_mode][$delta]['target_id'];
            _og_field_widget_replace_autocomplete_path($sub_element, $field_mode);

          }
        }
        else {
          // Tags widget, there's no delta, we can pass the element itself.
          _og_field_widget_replace_autocomplete_path($elements[$field_mode], $field_mode);
        }*/
      }
    }

    // Populate the 'array_parents' information in $form_state['field'] after
    // the form is built, so that we catch changes in the form structure performed
    // in alter() hooks.
    $elements['#after_build'][] = 'field_form_element_after_build';
    $elements['#field_name'] = $field_name;
    $elements['#field_parents'] = $parents;
    // Enforce the structure of submitted values.
    $elements['#parents'] = array_merge($parents, array($field_name));
    // Most widgets need their internal structure preserved in submitted values.
    $elements += array('#tree' => TRUE);

    $return = array(
      $field_name => array(
        // Aid in theming of widgets by rendering a classified container.
        '#type' => 'item',
        // Add title and description
        '#title' => check_plain($this->fieldDefinition->getFieldLabel()),
        '#description' => field_filter_xss(\Drupal::token()->replace($this->fieldDefinition->getFieldDescription())),
        // Assign a different parent, to keep the main id for the widget itself.
        '#parents' => array_merge($parents, array($field_name . '_wrapper')),
        '#attributes' => array(
          'class' => array(
            'field-type-' . drupal_html_class($this->fieldDefinition->getFieldType()),
            'field-name-' . drupal_html_class($field_name),
            'field-widget-' . drupal_html_class($this->getPluginId()),
          ),
        ),
        '#access' => $items->access('edit'),
        'widget' => $elements,
      ),
    );

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function elementValidate($element, &$form_state, $form) {}

  /**
   * {@inheritdoc}

  public function formMultipleElements(\Drupal\Core\Entity\EntityInterface $entity, \Drupal\field\FieldInterface $items, $langcode, array &$form, array &$form_state) {
    return;
    $element = array(
      '#title' => '',
      '#description' => '',
    );
    return $this->formSingleElement($entity, $items, 0, $langcode, $element, $form, $form_state);
  }
   */
}
