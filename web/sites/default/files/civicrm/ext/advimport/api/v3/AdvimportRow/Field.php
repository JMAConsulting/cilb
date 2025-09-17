<?php

/**
 * AdvimportRow.field API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_advimport_row_field($params) {
  $result = [
    'values' => [],
  ];

  $classname = null;
  $table_name = null;

  $api4 = \Civi\Api4\Advimport::get(false)
    ->addWhere('id', '=', $params['id'])
    ->addSelect('table_name', 'classname');

  $advimport = $api4->execute()->first();

  if (empty($advimport['classname'])) {
    throw new Exception('Advimport not found');
  }

  $classname = $advimport['classname'];
  $helper = new $classname();

  $dao = CRM_Core_DAO::executeQuery('SELECT * FROM %1 LIMIT 1', [
    1 => [$advimport['table_name'], 'MysqlColumnNameOrAlias'],
  ]);

  // This returns data keyed by their SQL column names.
  // DataTables, for example, will extract those keys for the column names.
  // We could get the headers from the mapping/helper class but not all imports
  // define a mapping function, and even if they did, it does not mean that all
  // fields are imported.
  // We do populate metadata from the helper/mapping, such as required/readonly.

  $null = NULL;
  $mapping = $helper->getMapping($null);

  if ($dao->fetch()) {
    $v = $dao->toArray();
    $keys = [];
    $keys = array_keys($v);

    foreach ($keys as $key) {
      $options = [];

      // @todo If we had used a mapping, the key would be the key from the CSV
      // file, not the correct key/field from the mapping, so it will be necessary
      // to first lookup the correct key.
      if (method_exists($helper, 'getOptions')) {
        $options = $helper->getOptions($key);
      }

      $label = $mapping[$key]['label'] ?? $key;

      $result['values'][$key] = [
        'key' => $key,
        'label' => $label,
        'options' => $options,
        'required' => $mapping[$key]['required'] ?? false,
        'readonly' => $mapping[$key]['readonly'] ?? false,
        'validate' => $mapping[$key]['validate'] ?? '',
        'html_type' => $mapping[$key]['html_type'] ?? (!empty($options) ? 'select' : 'text'),
        'bulk_update' => $mapping[$key]['bulk_update'] ?? false,
      ];
    }
  }

  return $result;
}
