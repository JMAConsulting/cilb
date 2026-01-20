<?php

require_once 'sweetalert.civix.php';
use CRM_Sweetalert_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function sweetalert_civicrm_config(&$config) {
  _sweetalert_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function sweetalert_civicrm_install() {
  _sweetalert_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function sweetalert_civicrm_enable() {
  _sweetalert_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_coreResourceList().
 */
function sweetalert_civicrm_coreResourceList(&$items, $region) {
  if ($region === 'html-header') {
    $resources = \Civi::resources();

    $resources
      ->addStyleFile(E::SHORT_NAME, 'css/sweetalert2.min.css', 0, $region)
      ->addScriptFile(E::SHORT_NAME, 'js/sweetalert2.min.js', 0, $region)
      ->addVars('sweetalert', ['darkMode' => _sweetalert_get_dark_mode()]);

    switch (\Civi::settings()->get('sweetalert_override_mode')) {
      case 'nowhere':
        break;

      case 'everywhere':
        $resources->addScriptFile(E::SHORT_NAME, 'js/crm-alert-everywhere.js', -10, $region);
        break;

      default:
        $resources->addScriptFile(E::SHORT_NAME, 'js/crm-alert-frontend.js', -10, $region);
        break;
    }
  }
}

/**
 * This uses the riverlea dark mode setting and maps it to sweetalert:
 * light=>light, dark=>dark, inherit=>auto
 *
 * @return string
 */
function _sweetalert_get_dark_mode() {
  if (CRM_Utils_System::isFrontendPage()) {
    $darkMode = \Civi::settings()->get('riverlea_dark_mode_frontend');
  }
  else {
    $darkMode = \Civi::settings()->get('riverlea_dark_mode_backend');
  }
  switch ($darkMode) {
    case 'dark':
    case 'light':
      return $darkMode;

    case 'inherit':
    default:
      return 'auto';
  }
}

/**
 * Implements hook_civicrm_buildForm()
 */
function sweetalert_civicrm_buildForm($formName, &$form) {
  // Check for messages
  $messages = CRM_Sweetalert_Utils::getStatus();
  if (empty($messages)) {
    return;
  }
  Civi::resources()->addVars('sweetalert', [
    'messages' => $messages,
  ]);
  Civi::resources()->addScript("
    CRM.vars.sweetalert.messages.forEach((element) => {
      swal.fire({
        title: element.title,
        text: element.text,
        icon: element.type
      });
    });
  ");
}
