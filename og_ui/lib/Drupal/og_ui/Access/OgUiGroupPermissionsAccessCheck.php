<?php
/**
 * @file
 * Contains \Drupal\og_ui\Access\OgUiGroupPermissionsAccessCheck.
 */

namespace Drupal\og_ui\Access;

use Drupal\Core\Access\AccessCheckInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;

class OgUiGroupPermissionsAccessCheck implements AccessCheckInterface {
  /**
   * A user account to check access for.
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Constructs an OgUiAccessCheck object.
   *
   * @param \Drupal\Core\Session\AccountInterface
   *   The user account to check access for.
   */
  public function __construct(AccountInterface $account) {
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route) {
    return array_key_exists('_og_ui_access_group_permissions', $route->getRequirements());
  }

  /**
   * Menu access; Check access and validate values for group permissions page.
   *
   * @param $rid
   * @param $group_type
   * @param $gid
   * @param $bundle
   */
  public function access(Route $route, Request $request, AccountInterface $account) {
    $rid = $request->attributes->get('rid');
    $group_type = $request->attributes->get('group_type');
    $gid = $request->attributes->get('gid');
    $bundle = $request->attributes->get('bundle');

    if (!$this->account->hasPermission('administer group')) {
      return self::DENY;
    }

    if ($group_type && !\Drupal::service('plugin.manager.entity')->getDefinition($group_type)) {
      return self::DENY;
    }
    if ($gid && !entity_load($group_type, $gid)) {
      return self::DENY;
    }

    if ($rid) {
      $og_roles = og_roles($group_type, $bundle, $gid);
      return !empty($og_roles[$rid]) ? self::ALLOW : self::DENY;
    }

    return self::ALLOW;
  }
} 
