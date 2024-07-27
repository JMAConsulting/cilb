<?php
/**
 * @file
 * Admin interface for Authorize.net webform module.
 */

/**
 * Page callback for admin settings page.
 */
function authorizenetwebform_admin_settings() {
  $form = array();

  $form['authorizenetwebform_acceptjs'] = array(
    '#type' => 'checkbox',
    '#title' => t('Use JavaScript to process payments'),
    '#description' => t('Minimize your PCI compliance requirements by sending payment data directly to Authorize.Net. Using !accept ensures that payment data will not reach your server.',
      array('!accept' => l(t('Accept.js'), 'https://developer.authorize.net/api/reference/features/acceptjs.html'))),
    '#default_value' => variable_get('authorizenetwebform_acceptjs', TRUE),
    '#access' => FALSE,
  );

  $form['authorizenetwebform_url'] = array(
    '#type' => 'radios',
    '#title' => t('Transaction mode'),
    '#description' => t('Unless you are using a sandbox account, this should be set to Live.'),
    '#options' => array(
      'main' => t('Live account'),
      'test' => t('Test (or Sandbox) account'),
    ),
    '#default_value' => variable_get('authorizenetwebform_url', 'main'),
  );
  // Add description text for URL choices.
  $form['authorizenetwebform_url']['test']['#description'] = t('Posts to https://test.authorize.net/gateway/transact.dll');
  $form['authorizenetwebform_url']['main']['#description'] = t('Posts to https://secure.authorize.net/gateway/transact.dll');

  $form['login'] = array(
    '#type' => 'fieldset',
    '#title' => t('Authorize.net Login'),
  );
  $form['login']['authorizenetwebform_login'] = array(
    '#type' => 'textfield',
    '#title' => t('API Login ID'),
    '#description' => t('Enter your Authorize.Net API Login ID - NOT your username.'),
    '#default_value' => variable_get('authorizenetwebform_login', ''),
  );
  $form['login']['authorizenetwebform_transaction'] = array(
    '#type' => 'textfield',
    '#title' => t('Transaction key'),
    '#description' => t('Enter your Authorize.Net Transaction Key.  It will not be displayed here once it is entered, for security purposes.  If you need to change it, please re-enter it.'),
    '#default_value' => variable_get('authorizenetwebform_transaction', ''),
  );
  $form['login']['authorizenetwebform_client_key'] = array(
    '#type' => 'textfield',
    '#title' => t('Public Client Key'),
    '#description' => t('Enter your Authorize.Net Transaction Key.  It will not be displayed here once it is entered, for security purposes.  If you need to change it, please re-enter it.'),
    '#default_value' => variable_get('authorizenetwebform_client_key', ''),
    '#states' => array(
      'visible' => array(
         ':input[name="authorizenetwebform_acceptjs"]' => array('checked' => TRUE),
       ),
    ),
  );

  $form['field_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Authorize.Net custom field settings'),
    '#description' => t('Allows you to configure your custom field settings to show in the field selection for Authorize.Net webforms'),
  );
  $form['field_settings']['authorizenetwebform_custom_fields'] = array(
    '#type' => 'textarea',
    '#title' => t("Custom Fields"),
    '#default_value' => authorizenet_create_custom_field_spec(variable_get('authorizenetwebform_custom_fields', NULL)),
    '#description' => t('A list of additional fields configured for transactions to Authorize.Net. One field per line, with field name and description seperated by pipes. i.e. safe_key|Some readable option.  See the Implementation Guide at http://developer.authorize.net/guides/AIM/ for more details.'),
    '#rows' => 5,
  );

  $form['#validate'][] = 'authorizenetwebform_parse_custom_field_spec';

  return system_settings_form($form);
}

/**
 * Validation handler for authorizenetwebform_admin_settings().
 */
function authorizenetwebform_parse_custom_field_spec($form, &$form_state) {
  $custom_fields = array();
  $custom_field_spec = $form_state['values']['authorizenetwebform_custom_fields'];
  if ( !empty($custom_field_spec) ) {
    $custom_field_tmp = explode("\n", $custom_field_spec);
    foreach ( $custom_field_tmp as $field ) {
      if ( empty($field) ) break ;
      $new_field = explode("|", $field);
      $custom_fields[$new_field[0]] = $new_field[1];
    }
  }

  form_set_value($form['field_settings']['authorizenetwebform_custom_fields'], $custom_fields, $form_state);
}

/**
 * Helper function: Generates default values.
 *
 * @see authorizenetwebform_admin_settings().
 */
function authorizenet_create_custom_field_spec($custom_fields) {
  if ( empty($custom_fields) ) {
    return '';
  }

  $spec = '';
  foreach ( array_keys($custom_fields) as $field ) {
    $field_string = $field . "|" . $custom_fields[$field] . "\n";
    $spec .= $field_string;
  }
  return $spec;
}
