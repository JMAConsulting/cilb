<?php

use CRM_CilbReports_ExtensionUtil as E;

class CRM_CilbReports_BAO_CountyCode extends CRM_CilbReports_DAO_CountyCode {

public static function retrieve(array $params) {
     $options = [];
     $properties = \Civi\Api4\CountyCode::get(FALSE)
     ->addClause('OR', ['id', 'LIKE', $params['id']], ['county_code', 'LIKE', $params['name']['LIKE']])
      //->addClause('address_id.name', 'LIKE', $params['name']['LIKE'])
      ->setLimit(100)
      ->execute();
    foreach ($properties as $property) {
      $options[$property['id']] = $property;
    }
//print_r($params);
    return $options;
}


}
