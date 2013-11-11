<?php

/**
 * @file
 * Definition of Drupal\og\Entity\OgMembershipType.
 * A class used for group membership types.
 */

namespace Drupal\og\Entity;

use Drupal\Core\Entity\Entity;

/**
 * Defines the OgMembership entity class.
 *
 * @EntityType(
 *   id = "og_membership_type",
 *   label = @Translation("OG Membership Type"),
 *   controllers = {
 *      "storage" = "Drupal\Core\Entity\DatabaseStorageController"
 *   },
 *   base_table = "og_membership_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class OgMembershipType extends Entity {

  public $name;
  public $description = '';

  public function __construct($values = array()) {
    parent::__construct($values, 'og_membership_type');
  }
}
