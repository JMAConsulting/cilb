<?php
namespace Civi\Api4;

/**
 * Authnet Utilities.
 *
 * Utilities provided by Authnet extension.
 *
 * @package Civi\Api4
 */
class AuthnetUtilities extends Generic\AbstractEntity {

  public static function getFields() {
    return new Generic\BasicGetFieldsAction(__CLASS__, __FUNCTION__, function() {
      return [ ];
    });
  }
}
