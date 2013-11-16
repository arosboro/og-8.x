<?php

/**
 * @file
 * Contains \Drupal\node\Entity\NodeInterface.
 */

namespace Drupal\og;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface defining a og_membership entity.
 */
interface OgMembershipInterface extends ContentEntityInterface, EntityChangedInterface {
  /**
   * Return the group associated with the OG membership.
   */
  public function group();

  /**
   * Gets the associated OG membership type.
   *
   * @return OgMembershipType
   */
  public function type();
}
