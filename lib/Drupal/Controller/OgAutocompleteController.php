<?php

/**
 * Menu callback: autocomplete the label of an entity.
 *
 * @param $type
 *   The widget type (i.e. 'single' or 'tags').
 * @param $field_name
 *   The name of the entity-reference field.
 * @param $entity_type
 *   The entity type.
 * @param $bundle_name
 *   The bundle name.
 * @param $field_mode
 *   The field mode, "default" or "admin".
 * @param $entity_id
 *   Optional; The entity ID the entity-reference field is attached to.
 *   Defaults to ''.
 * @param $string
 *   The label of the entity to query by.
 *
 * @see entity_reference_autocomplete_callback()
 */
function og_entityreference_autocomplete_callback($type, $field_name, $entity_type, $bundle_name, $field_mode, $entity_id = '', $string = '') {
  $field = field_info_field($field_name);
  $instance = field_info_instance($entity_type, $field_name, $bundle_name);
  $instance = og_get_mocked_instance($instance, $field_mode);

  if (!$field || !$instance || $field['type'] != 'entityreference' || !field_access('edit', $field, $entity_type)) {
    return MENU_ACCESS_DENIED;
  }

  return entityreference_autocomplete_callback_get_matches($type, $field, $instance, $entity_type, $entity_id, $string);
}