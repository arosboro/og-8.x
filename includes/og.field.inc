<?php

/**
 * Helper function; Get the mocked widget.
 */
function og_get_mocked_widget($widget_type, $field) {
  $widget_definition = \Drupal::service('plugin.manager.field.widget')->getDefinition($widget_type);
  return new $widget_definition['class']($widget_type, $widget_definition, $field, $widget_definition['settings']);
}

/**
 * Rebuild the element's values, using the default and admin if exists.
 */
function og_complex_widget_element_validate($element, &$form_state, $form) {
  $parents = array_slice($element['#parents'], 0, -1);
  $subform = \Drupal\Component\Utility\NestedArray::getValue($form_state['values'], $parents);

  $ids = $element['#other_groups_ids'];
  foreach (array('default', 'admin') as $type) {
    if (empty($subform[$type]) || !is_array($subform[$type])) {
      continue;
    }

    foreach ($subform[$type] as $delta => $value) {
      if (!empty($value['target_id']) && is_numeric($value['target_id'])) {
        $ids[] = array('target_id' => $value['target_id']);
        continue;
      }
      else if(is_numeric($value)) {
        $ids[] = array('target_id' => $value);
      }
    }
  }

  // Set the form values by directly using drupal_array_set_nested_values(),
  // which allows us to control the element parents. In this case we cut off the
  // last element that contains the delta 0, as $ids is already keyed with
  // deltas.
  $field_parents = array_slice($parents, 0, -2);
  $field_name = array_shift($field_parents);
  //\Drupal\Component\Utility\NestedArray::setValue($form_state['values'], $field_parents, $ids, TRUE);
  $form_state['values'][$field_name] = $ids;

  // If the element is required, ensure that at least one group has been chosen.
  if ($element['#required']) {
    $subform = \Drupal\Component\Utility\NestedArray::getValue($form_state['values'], $parents);
    if (empty($subform)) {
      form_error($element, t('!name field is required.', array('!name' => $element['#title'])));
    }
  }
}

/**
 * Replace Entity-reference's autocomplete path with our own.
 *
 * @param $element
 *   The form element, passed by reference.
 * @param $field_mode
 *   The field mode.
 *
 */
function _og_field_widget_replace_autocomplete_path(&$element, $field_mode) {
  // Rebuild the autocomplete path.
  $path = explode('/', $element['#autocomplete_path']);
  $element['#autocomplete_path'] = 'og/autocomplete';

  // Add autocomplete type
  $element['#autocomplete_path'] .= "/$path[2]/$path[3]/$path[4]/$path[5]";

  // Add field mode.
  $element['#autocomplete_path'] .= "/$field_mode";

  // Add the entity ID.
  $element['#autocomplete_path'] .= "/$path[6]";
  if (!empty($path[7])) {
    // Add the text.
    $element['#autocomplete_path'] .= "/$path[7]";
  }
}