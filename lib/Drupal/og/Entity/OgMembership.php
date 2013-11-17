<?php

/**
 * @file
 * Definition of Drupal\og\Entity\OgMembership.
 * Main class for OG membership entities provided by Entity API.
 */

namespace Drupal\og\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\field\Field;
use Drupal\og\OgMembershipInterface;
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
 *   fieldable = TRUE,
 *   label_callback = "og_membership_label",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "description",
 *     "name" = "name",
 *     "uuid" = "uuid"
 *   },
 *   bundles = {},
 *   bundle_keys = {
 *     "bundle" = "name"
 *   }
 * )
 *
 *  // TODO
 *  'module' => 'og',
 *  'metadata controller class' => 'OgMembershipMetadataController',
 *  'views controller class' => 'OgMembershipViewsController',
 *  'access callback' => 'og_membership_access',
 */
class OgMembership extends ContentEntityBase implements OgMembershipInterface {
  public function __construct(array $values = array(), $entityType = NULL) {
    parent::__construct($values, 'og_membership');
  }

  public static function baseFieldDefinitions($entity_type) {
    $properties['id'] = array(
      'label' => t('Group membership ID'),
      'description' => t("The group membership's unique ID."),
      'type' => 'integer_field',
      'read-only' => TRUE,
    );

    $properties['type'] = array(
      'label' => t('Group membership type'),
      'description' => 'Reference to a group membership type.',
      'type' => 'text_field',
      'read-only' => TRUE,
    );

    $properties['etid'] = array(
      'label' => t('Entity ID'),
      'description' => t('The entity ID.'),
      'type' => 'integer_field',
      'read-only' => TRUE,
    );

    $properties['entity_type'] = array(
      'label' => t('Entity type'),
      'description' => t("The entity type (e.g. node, comment, etc)."),
      'type' => 'text_field',
      'read-only' => TRUE,
    );

    $properties['gid'] = array(
      'label' => t('Group ID'),
      'description' => t("The group's unique ID."),
      'type' => 'integer_field',
      'read-only' => TRUE,
    );

    $properties['group_type'] = array(
      'label' => t('Group type'),
      'description' => t("The group's entity type (e.g. node, comment, etc)."),
      'type' => 'text_field',
      'read-only' => TRUE,
    );

    $properties['state'] = array(
      'label' => t('State'),
      'description' => t('The state of the group content.'),
      'type' => 'integer_field',
      'read-only' => TRUE,
    );

    $properties['created'] = array(
      'label' => t('created'),
      'description' => t('The Unix timestamp when the group content was created.'),
      'type' => 'integer_field',
      'read-only' => TRUE,
    );

    $properties['field_name'] = array(
      'label' => t('Field name'),
      'description' => t("The name of the field holding the group ID, the OG memebership is associated with."),
      'type' => 'text_field',
      'read-only' => TRUE,
    );

    $properties['language'] = array(
      'label' => t('Language'),
      'description' => t('The language of this membership.'),
      'type' => 'text_field',
      'read-only' => TRUE,
    );

    return $properties;
  }

  public function id() {
    return $this->get('id')->value;
  }

  /**
   * Gets the associated OG membership type.
   *
   * @return OgMembershipType
   */
  public function type() {
    return $this->get('type')->value;
  }

  public function etid() {
    return $this->get('etid')->value;
  }

  public function entity_type() {
    return $this->get('entity_type')->value;
  }

  public function gid() {
    return $this->get('gid')->value;
  }

  public function group_type() {
    return $this->get('group_type')->value;
  }

  public function state() {
    return $this->get('state')->value;
  }

  public function created() {
    return $this->get('created')->value;
  }

  public function field_name() {
    return $this->get('field_name')->value;
  }

  public function language() {
    return $this->get('language')->value;
  }

  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * Override Entity::save().
   *
   * Make sure we can save an OG membership, and that it is properly registered
   * under a valid field.
   */
  public function save() {
    $entity_type = $this->entity_type();
    $etid = $this->etid();
    dpm($entity_type, 'entity_type');
    dpm($etid, 'etid');

    if ($entity_type == 'user' && !$etid) {
      throw new OgException('OG membership can not be created for anonymous user.');
    }

    $entity = entity_load($entity_type, $etid);
    $bundle = $entity->bundle();

    $group_type = $this->group_type();
    $gid = $this->gid();
    $group = entity_load($group_type, $gid);
    $group_bundle = $group->bundle();

    $field_name = $this->field_name();

    // Placeholder for exceptions, in case we need to throw one.
    $params = array(
      '%entity-type' => $entity_type,
      '%entity-bundle' => $bundle,
      '%etid' => $this->etid(),
      '%group-type' => $group_type,
      '%group-bundle' => $group_bundle,
      '%gid' => $this->gid(),
      '%field-name' => $field_name,
    );

    $field = Field::fieldInfo()->getField($entity_type, $field_name);
    $settings = $field->getFieldSettings();

    if (!$field || !Field::fieldInfo()->getInstance($entity_type, $bundle, $field_name)) {
      throw new OgException(format_string('OG membership can not be created in entity %entity-type and bundle %entity-bundle using the field %field-name as the field does not exist.', $params));
    }

    if (!og_is_group_audience_field($entity_type, $field_name)) {
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
}