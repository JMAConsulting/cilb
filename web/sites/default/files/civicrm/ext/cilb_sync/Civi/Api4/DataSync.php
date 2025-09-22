<?php

namespace Civi\Api4;

class DataSync extends Generic\AbstractEntity {

  /**
   * @var array[]
   */
  public static $entityFields = [
  ];

  /**
   * @param bool $checkPermissions
   * @return Generic\BasicGetFieldsAction
   */
  public static function getFields($checkPermissions = TRUE) {
    return (new Generic\BasicGetFieldsAction('Entity', __FUNCTION__, function(Generic\BasicGetFieldsAction $getFields) {
      return Entity::$entityFields;
    }))->setCheckPermissions($checkPermissions);
  }

  /**
   * @param bool $checkPermissions
   * @return Generic\AutocompleteAction
   */
  public static function autocomplete($checkPermissions = TRUE) {
    return (new Generic\AutocompleteAction('Entity', __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * @return array
   */
  public static function permissions() {
    return [
      'default' => ['administer CiviCRM'],
    ];
  }

}
