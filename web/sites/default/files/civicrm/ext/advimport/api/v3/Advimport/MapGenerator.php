<?php 

function _civicrm_api3_advimport_map_generator_spec(&$params) {
  $params['input']['api.required'] = 1;
}

/**
 * Advimport.map_generator API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_advimport_map_generator($params) {
  $result = [
    'values' => [],
  ];

  if (!empty($params['input'])) {
    $headers = str_getcsv($params['input']);
    $output = "    \$map = [\n";

    foreach ($headers as $h) {
      $output .= "      '$h' => [
        'label' => '$h',
        'field' => '$h',
        'required' => FALSE,
        'validate' => 'String',
        'example' => '1234',
      ],\n";
    }

    $output .= "    ];\n";

    print_r($output);

    // This does not output nicely (displays \n)
    // $result['code'] = $output;
  }

  return $result;
}
