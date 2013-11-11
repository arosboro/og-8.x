<?php

/**
 * @file
 * Definition of Drupal\og\Entity\OgMembership.
 * Main class for OG membership entities provided by Entity API.
 */

namespace Drupal\og\Entity;

use Drupal\Core\Entity\Entity;
use Drupal\og\OgException;

/**
 * Defines the OgMembership entity class.
 *
 * @EntityType(
 *   id = "og_membership",
 *   label = @Translation("OG Membership"),
 *   controllers = {
 *      "storage" = "Drupal\Core\Entity\FieldableDatabaseStorageController"
 *   },
 *   base_table = "og_membership",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class OgMembership extends Entity {
  public function __construct(array $values = array(), $entityType = NULL) {
    parent::__construct($values, 'og_membership');
  }

  /**
   * Override Entity::save().
   *
   * Make sure we can save an OG membership, and that it is properly registered
   * under a valid field.
   */
  public function save() {
    $entity_type = $this->entity_type;
    $etid = $this->etid;

    if ($entity_type == 'user' && !$etid) {
      throw new OgException('OG membership can not be created for anonymous user.');
    }

    $entity = entity_load($entity_type, $etid);
    $bundle = $entity->bundle();

    $group_type = $this->group_type;
    $gid = $this->gid;
    $group = entity_load($group_type, $gid);
    $group_bundle = $group->bundle();

    $field_name = $this->field_name;

    // Placeholder for exceptions, in case we need to throw one.
    $params = array(
      '%entity-type' => $entity_type,
      '%entity-bundle' => $bundle,
      '%etid' => $this->etid,
      '%group-type' => $group_type,
      '%group-bundle' => $group_bundle,
      '%gid' => $this->gid,
      '%field-name' => $field_name,
    );

    $field = Field::fieldInfo()->getField($field_name);
    $settings = $field->getFieldSettings();

    if (!$field || !Field::fieldInfo()->getInstance($entity_type, $bundle, $field_name)) {
      throw new OgException(format_string('OG membership can not be created in entity %entity-type and bundle %entity-bundle using the field %field-name as the field does not exist.', $params));
    }

    if (!og_is_group_audience_field($field_name)) {
      throw new OgException(format_string('OG membership can not be created with %field-name as it is not a valid group-audience type.', $params));
    }

    if ($settings['target_type'] != $group_type) {
      throw new OgException(format_string('OG membership can not be created in entity %entity-type and bundle %entity-bundle using the field %field-name as the field does not reference %group-type entity type.', $params));
    }

    if (!empty($settings['handler_settings']['target_bundles']) && !in_array($group_bundle, $settings['handler_settings']['target_bundles'])) {
      throw new OgException(format_string('OG membership can not be created in entity %entity-type and bundle %entity-bundle using the field %field-name as the field does not reference %group-bundle bundle in %group-type entity type.', $params));
    }

    $params += array('@cardinality' => $field->getFieldCardinality());
    // Check field cardinality, that we may add another value, if we have a new
    // OG membership.
    if (empty($this->id) && !og_check_field_cardinality($entity_type, $etid, $field_name)) {
      throw new OgException(format_string('OG membership can not be created in entity %entity-type and bundle %entity-bundle using the field %field-name as the field cardinality is set to @cardinality.', $params));
    }

    if (empty($this->id) && $og_membership = og_get_membership($this->group_type, $this->gid, $this->entity_type, $this->etid)) {
      throw new OgException(format_string('OG membership for %etid - %entity-type in group %gid - %group-type already exists.', $params));
    }

    // We can now safely save the entity.
    parent::save();
    og_membership_invalidate_cache();
    // Clear the group content entity field cache.
    cache('field')->deleteMultiple(array("field:$entity_type:$etid"));

    // Supporting the entity cache module.
    // TODO: is entitycache necessary in Drupal 8?
    /*
    if (module_exists('entitycache')) {
      cache_clear_all($etid, 'cache_entity_' . $entity_type);
    }
    */
  }

  public function delete() {
    parent::delete();

    og_membership_invalidate_cache();
    // Clear the group content entity field cache.
    cache('field')->deleteMultiple(array("field:{$this->entity_type}:{$this->$etid}"));

    // Supporting the entity cache module.
    // TODO: is entitycache necessary in Drupal 8?
    /*
    if (module_exists('entitycache')) {
      cache_clear_all($this->etid, 'cache_entity_' . $this->entity_type);
    }
    */
  }

  /**
   * Return the group associated with the OG membership.
   */
  public function group() {
    return entity_load($this->group_type, $this->gid);
  }

  /**
   * Gets the associated OG membership type.
   *
   * @return OgMembershipType
   */
  public function type() {
    return og_membership_type_load($this->name);
  }
}