<?php

namespace Drupal\stream_menu\Access;

use Drupal\Core\Access\AccessCheckInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Custom access check for Stream menu link.
 */
class StreamMenuAccessCheck implements AccessCheckInterface {

  /**
   * Checks the access to the stream link.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    if ($account->isAuthenticated() && $account->hasRole('student')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Checks if this access check applies to the route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route object.
   *
   * @return bool
   *   TRUE if the access check applies, FALSE otherwise.
   */
  public function applies(Route $route) {
    return $route->hasRequirement('_custom_access') && $route->getRequirement('_custom_access') === 'stream_menu.access_check';
  }

}
