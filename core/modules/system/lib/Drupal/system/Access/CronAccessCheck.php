<?php

/**
 * @file
 * Contains Drupal\system\Access\CronAccessCheck.
 */

namespace Drupal\system\Access;

use Drupal\Core\Access\AccessCheckInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Access check for cron routes.
 */
class CronAccessCheck implements AccessCheckInterface {

  /**
   * Implements AccessCheckInterface::applies().
   */
  public function applies(Route $route) {
    return array_key_exists('_access_system_cron', $route->getRequirements());
  }

  /**
   * Implements AccessCheckInterface::access().
   */
  public function access(Route $route, Request $request) {
    $key = $request->attributes->get('key');
    if ($key != \Drupal::state()->get('system.cron_key')) {
      watchdog('cron', 'Cron could not run because an invalid key was used.', array(), WATCHDOG_NOTICE);
      return FALSE;
    }
    elseif (config('system.maintenance')->get('enabled')) {
      watchdog('cron', 'Cron could not run because the site is in maintenance mode.', array(), WATCHDOG_NOTICE);
      return FALSE;
    }
    return TRUE;
  }
}
