<?php

namespace Civi\Api4;

/**
 * A collection of system maintenance/diagnostic utilities.
 *
 * @searchable none
 * @since 5.19
 * @package Civi\Api4
 */
class PaymentMJW extends Generic\AbstractEntity {

  /**
   * @param bool $checkPermissions
   * @return Generic\BasicGetFieldsAction
   */
  public static function getFields($checkPermissions = TRUE) {
    return (new Generic\BasicGetFieldsAction(__CLASS__, __FUNCTION__, function() {
      return [];
    }))->setCheckPermissions($checkPermissions);
  }

  /**
   * @param bool $checkPermissions
   * @return Action\PaymentMJW\Refund
   */
  public static function refund($checkPermissions = TRUE): Action\PaymentMJW\Refund {
    return (new Action\PaymentMJW\Refund(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * Refunding is gated on 'refund contributions', matching this extension's
   * refund form and contribution link.
   *
   * @see \Civi\Api4\Generic\AbstractEntity::permissions()
   * @return array
   */
  public static function permissions() {
    $permissions = parent::permissions();
    $permissions['refund'] = ['refund contributions'];
    return $permissions;
  }

}
