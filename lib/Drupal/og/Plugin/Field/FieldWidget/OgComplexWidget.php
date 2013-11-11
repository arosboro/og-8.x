<?php

/**
 * @file
 * Contains \Drupal\og\Plugin\Field\FieldWidget\OgComplexWidget.
 */

namespace Drupal\og\Plugin\Field\FieldWidget;

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
 *   }
 * )
 */
class OgComplexWidget extends AutocompleteWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, array &$form_state) {
    // We let the Field API handles multiple values for us, only take care of
    // the one matching our delta.
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
    $settings = $field->getFieldSettings();

    if ($settings['handler'] != 'og' && strpos($settings['handler'], 'og_') !== 0) {
      $params = array('%label' => $field->label);
      \Drupal::formBuilder()->setError($form, t('Field %label is a group-audience but its Entity selection mode is not defined as "Organic groups" in the field settings page.', $params));
      return parent::formElement($items, $delta, $element, $form, $form_state);
    }

    // Cache the processed entity, to make sure we call the widget only once.
    $cache = &drupal_static(__FUNCTION__, array());
    $id = $entity->id();
    $bundle = $entity->bundle();
    $field_name = $field->getFieldName();

    $identifier = $field_name . ':' . $entity_type . ':' . $bundle . ':' . $id;
    if (isset($cache[$identifier])) {
      //return array();
    }
    $cache[$identifier] = TRUE;

    $field_modes = array('default');
    $has_admin = FALSE;

    // The group IDs that might not be accessible by the user, but we need
    // to keep even after saving.
    $element['#other_groups_ids'] = array();
    $element['#element_validate'][] = 'OgComplexWidget::elementValidate';

    global $user;
    if ($user->hasPermission('administer group')) {
      $has_admin = TRUE;
      $field_modes[] = 'admin';
    }

    // Build an array of entity IDs. Field's $items are loaded
    // in OgBehaviorHandler::load().
    $entity_gids = array();
    foreach ($items as $item) {
      $entity_gids[] = $item['target_id'];
    }


    $target_type = $field->getFieldSetting('target_type');

    $user_gids = og_get_entity_groups();
    $user_gids = !empty($user_gids[$target_type]) ? $user_gids[$target_type] : array();

    // Get the "Other group" group IDs.
    $other_groups_ids = array_diff($entity_gids, $user_gids);

    $instance = Field::fieldInfo()->getInstance($entity_type, $bundle, $field_name);

    foreach ($field_modes as $field_mode) {
      $widget_type = $instance->settings['behaviors']['og_widget'][$field_mode]['widget_type'];
      $widget_definition = \Drupal::service('plugin.manager.field.widget')->getDefinition($widget_type);

      if ($has_admin) {
        if ($id) {
          // The field might be required, and it will throw an exception
          // when we try to set an empty value, so change the wrapper's
          // info.
//          $wrapper = entity_metadata_wrapper($entity_type, $dummy_entity, array('property info alter' => 'og_property_info_alter', 'field name' => $field_name));
          if ($field_mode == 'admin') {
            // Keep only the hidden group IDs on the entity, so they won't
            // appear again on the "admin" field, for example on an autocomplete
            // widget type.
            $valid_ids = $other_groups_ids ? SelectionPluginManager::getSelectionHandler($field)->validateReferencableEntities($other_groups_ids) : array();
            $valid_ids = $field['cardinality'] == 1 ? reset($valid_ids) : $valid_ids;
            $field->set('target_id', $valid_ids ? $valid_ids : NULL);
          }
          else {
            // Keep only the groups that belong to the user and to the entity.
            $my_group_ids = array_values(array_intersect($user_gids, $entity_gids));
            $valid_ids = $my_group_ids ? SelectionPluginManager::getSelectionHandler($field)->validateReferencableEntities($my_group_ids) : array();

            $valid_ids = $field['cardinality'] == 1 ? reset($valid_ids) : $valid_ids;
            $field->set('target_id', $valid_ids ? $valid_ids : NULL);
          }
        }

        dpm($widget_definition);
        $widget = new $widget_definition['class']($widget_type, $widget_definition, $field, $widget_definition['settings']);
        $mocked_element = $widget->formElement($items, $delta, $element, $form, $form_state);
        dpm($mocked_element);

        $mocked_element['#required'] = FALSE;
        if ($field_mode == 'default') {
          $mocked_element['#title'] = t('Your groups');
          if ($entity_type == 'user') {
            $mocked_element['#description'] = t('Associate this user with groups you belong to.');
          }
          else {
            $mocked_element['#description'] = t('Associate this content with groups you belong to.');
          }
        }
        else {
          $mocked_element['#title'] = t('Other groups');
          if ($entity_type == 'user') {
            $mocked_element['#description'] = t('As groups administrator, associate this user with groups you do <em>not</em> belong to.');
          }
          else {
            $mocked_element['#description'] = t('As groups administrator, associate this content with groups you do <em>not</em> belong to.');
          }
        }
      }
      else {
        // Non-admin user.
        /*$mocked_instance_other_groups = $mocked_instance;
        $mocked_instance_other_groups['field_mode'] = 'admin';*/
        if ($other_groups_ids && $valid_ids = SelectionPluginManager::getSelectionHandler($field)->validateReferencableEntities($other_groups_ids)) {
          foreach ($valid_ids as $id) {
            $element['#other_groups_ids'][] = array('target_id' => $id);
          }
        }
      }

      $dummy_form_state = $form_state;
      if (empty($form_state['rebuild'])) {
        // Form is "fresh" (i.e. not call from field_add_more_submit()), so
        // re-set the items-count, to show the correct amount for the mocked
        // instance.
        $dummy_form_state['field'][$field_name]['und']['items_count'] =  count($field->get('target_id'));
      }

      $new_element = ctools_field_invoke_field($mocked_instance, 'form', $entity_type, $dummy_entity, $form, $dummy_form_state, array('default' => TRUE));
      Field::WidgetBase->form($items, array &$form, array &$form_state)
      $element[$field_mode] = $new_element[$field_name][LANGUAGE_NONE];
      if (in_arrayy($widget_type, array('entity_reference_autocomplete', 'entity_reference_autocomplete_tags'))) {
        // Change the "Add more" button name so it adds only the needed
        // element.
        if (!empty($mocked_element[$field_mode]['add_more']['#name'])) {
          $mocked_element[$field_mode]['add_more']['#name'] .= '__' . $field_mode;
        }

        if ($widget_type == 'entity_reference_autocomplete') {
          dpm($mocked_element);
          foreach (array_keys($mocked_element[$field_mode]) as $delta) {
            if (!is_numeric($delta)) {
              continue;
            }

            $sub_element = &$mocked_element[$field_mode][$delta]['target_id'];
            _og_field_widget_replace_autocomplete_path($sub_element, $field_mode);

          }
        }
        else {
          // Tags widget, there's no delta, we can pass the element itself.
          _og_field_widget_replace_autocomplete_path($mocked_element[$field_mode], $field_mode);
        }
      }
    }

    return $mocked_element;
  }

  /**
   * {@inheritdoc}
   */
  public function elementValidate($element, &$form_state, $form) {
    $auto_create = $this->getSelectionHandlerSetting('auto_create');

    // If a value was entered into the autocomplete.
    $value = '';
    if (!empty($element['#value'])) {
      // Take "label (entity id)', match the id from parenthesis.
      // @todo: Lookup the entity type's ID data type and use it here.
      // https://drupal.org/node/2107249
      if ($this->isContentReferenced() && preg_match("/.+\((\d+)\)/", $element['#value'], $matches)) {
        $value = $matches[1];
      }
      elseif (preg_match("/.+\(([\w.]+)\)/", $element['#value'], $matches)) {
        $value = $matches[1];
      }
      if (!$value) {
        // Try to get a match from the input string when the user didn't use the
        // autocomplete but filled in a value manually.
        $handler = \Drupal::service('plugin.manager.entity_reference.selection')->getSelectionHandler($this->fieldDefinition);
        $value = $handler->validateAutocompleteInput($element['#value'], $element, $form_state, $form, !$auto_create);
      }

      if (!$value && $auto_create && (count($this->getSelectionHandlerSetting('target_bundles')) == 1)) {
        // Auto-create item. see entity_reference_field_presave().
        $value = array(
          'target_id' => 0,
          'entity' => $this->createNewEntity($element['#value'], $element['#autocreate_uid']),
          // Keep the weight property.
          '_weight' => $element['#weight'],
        );
        // Change the element['#parents'], so in form_set_value() we
        // populate the correct key.
        array_pop($element['#parents']);
      }
    }
    form_set_value($element, $value, $form_state);
  }
}
