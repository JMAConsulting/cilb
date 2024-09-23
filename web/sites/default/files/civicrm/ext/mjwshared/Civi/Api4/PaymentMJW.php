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

}
