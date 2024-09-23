<?php

namespace Civi\Api4;

class Cilb extends Generic\AbstractEntity {

  public static function getFields($checkPermissions = TRUE) {
    return (new Generic\BasicGetFieldsAction(__CLASS__, __FUNCTION__, function($getFieldsAction) {
      return [
        [
          'name' => 'id',
          'data_type' => 'Integer',
          'description' => 'Unique identifier. If it were named something other than "id" we would need to override the getInfo() function to supply "primary_key".',
        ],
      ];
    }))->setCheckPermissions($checkPermissions);
  }

}
