<?php

/**
 * Helper function; Get the mocked widget.
 */
function og_get_mocked_widget($widget_type, $field) {
  $widget_definition = \Drupal::service('plugin.manager.field.widget')->getDefinition($widget_type);
  return new $widget_definition['class']($widget_type, $widget_definition, $field, $widget_definition['settings']);
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