<?php

/**
 * Helper function; Get the mocked instance.
 */
function og_get_mocked_instance($instance, $field_mode) {
  $mocked_instance = $instance;

  $widget_type = $instance->settings['behaviors']['og_widget'][$field_mode]['widget_type'];
  dpm($instance, $field_mode);

  $mocked_widget = array();
  $mocked_widget['type'] = $widget_type;

  $options = entity_get_form_display($instance->entity_type, $instance->bundle, 'default')->getComponent(OG_AUDIENCE_FIELD);
  $plugin_definition = \Drupal::service('plugin.manager.field.widget')->getDefinition($options['type']);
  dpm($plugin_definition, $options['type']);
  $widget_definition = \Drupal::service('plugin.manager.field.widget')->getDefinition($widget_type);
  dpm($widget_definition, $widget_type);
  $mocked_widget['type'] = $widget_type;
  // Set the widget's module.
  $mocked_widget['module'] = $widget_defintion['module'];
  $mocked_widget['settings'] = drupal_array_merge_deep($plugin_definition['settings'], $widget_definition['settings']);

  // See OgSelectionHandler::buildEntityFieldQuery().
  $mocked_instance['field_mode'] = $field_mode;
  return $mocked_instance;
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