<?php

/**
 * @file
 * Post update functions for User Email Verification module.
 */

use Drupal\user\Entity\Role;

/**
 * Grant "manage user email verification settings" permission to related roles.
 */
function user_email_verification_post_update_grant_manage_permission() {
  $grant_permission = function (Role $role) {
    if ($role->hasPermission('administer account settings')) {
      $role->grantPermission('manage user email verification settings');
      $role->save();
    }
  };
  array_map($grant_permission, Role::loadMultiple());
}

/**
 * Enable user account delete after "Extended verification time interval".
 */
function user_email_verification_post_update_set_extended_end_delete_account() {
  \Drupal::configFactory()
    ->getEditable('user_email_verification.settings')
    ->set('extended_end_delete_account', TRUE)
    ->save();
}
