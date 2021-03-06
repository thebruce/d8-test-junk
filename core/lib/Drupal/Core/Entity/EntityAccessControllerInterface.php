<?php

/**
 * @file
 * Contains \Drupal\Core\Entity\EntityAccessControllerInterface.
 */

namespace Drupal\Core\Entity;

use Drupal\Core\Language\Language;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines a common interface for entity access controller classes.
 */
interface EntityAccessControllerInterface {

  /**
   * Checks access to an operation on a given entity or entity translation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to check access.
   * @param string $operation
   *   The operation access should be checked for.
   *   Usually one of "view", "create", "update" or "delete".
   * @param string $langcode
   *   (optional) The language code for which to check access. Defaults to
   *   Language::LANGCODE_DEFAULT.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The user session for which to check access, or NULL to check
   *   access for the current user. Defaults to NULL.
   *
   * @return bool
   *   TRUE if access was granted, FALSE otherwise.
   */
  public function access(EntityInterface $entity, $operation, $langcode = Language::LANGCODE_DEFAULT, AccountInterface $account = NULL);

  /**
   * Clears all cached access checks.
   */
  public function resetCache();

}
