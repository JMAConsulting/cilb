<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

require_once 'mjwshared.civix.php';
use CRM_Mjwshared_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function mjwshared_civicrm_config(&$config) {
  _mjwshared_civix_civicrm_config($config);

  if (isset(Civi::$statics[__FUNCTION__])) { return; }
  Civi::$statics[__FUNCTION__] = 1;

  // Symfony hook priorities - see https://docs.civicrm.org/dev/en/latest/hooks/usage/symfony/#priorities
  // Make sure this runs after everything else but before minifier
  Civi::dispatcher()->addListener('hook_civicrm_buildAsset', 'mjwshared_symfony_civicrm_buildAsset', -990);
  // This should run before (almost) anything else as we're loading shared libraries
  Civi::dispatcher()->addListener('hook_civicrm_coreResourceList', 'mjwshared_symfony_civicrm_coreResourceList', 1000);

  \Civi::dispatcher()->addListener('civi.dao.preUpdate', 'mjwshared_symfony_preUpdateInsert');
  \Civi::dispatcher()->addListener('civi.dao.preInsert', 'mjwshared_symfony_preUpdateInsert');
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function mjwshared_civicrm_install() {
  _mjwshared_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function mjwshared_civicrm_enable() {
  _mjwshared_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_coreResourceList().
 */
function mjwshared_symfony_civicrm_coreResourceList($event, $hook) {
  if ($event->region === 'html-header') {
    // Load the CRM.payment library
    // We want this library loaded early. Weights are negative earlier, positive later (opposite to symfony).
    // CiviCRM "earliest" is -9999 we'll go with -2000 to load after CiviCRM core but before anything else.
    \Civi::resources()->addScriptFile(
      E::LONG_NAME,
      'js/crm.payment.js',
      -2000,
      $event->region
    );
  }
}

/**
 * Implements hook_civicrm_check().
 *
 * @throws \CRM_Core_Exception
 */
function mjwshared_civicrm_check(&$messages) {
  $checks = new CRM_Mjwshared_Check($messages);
  $messages = $checks->checkRequirements();
}

/**
 * @param \Civi\Core\Event\GenericHookEvent $event
 * @param $hook
 *
 * @throws \CRM_Core_Exception
 */
function mjwshared_symfony_civicrm_buildAsset($event, $hook) {
  $extensions = civicrm_api3('Extension', 'get', [
    'full_name' => "minifier",
  ]);
  if (empty($extensions['count']) || ($extensions['values'][$extensions['id']]['status'] !== 'installed')) {
    if (empty($event->content) && !empty($event->params['path'])) {
      $event->content = file_get_contents($event->params['path']);
    }
    if (empty($event->mimeType) && !empty($event->params['mimetype'])) {
      $event->mimeType = $event->params['mimetype'];
    }
  }
}

/**
 * Implements hook_civicrm_links
 * Add links to membership list on contacts tab to view/setup direct debit
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $links
 * @param $mask
 * @param $values
 */
function mjwshared_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  if (!CRM_Core_Permission::check('edit contributions')) {
    return;
  }

  switch ($objectName) {
    case 'Contribution':
      if ($op !== 'contribution.edit.action') {
        return;
      }
      if ((boolean)\Civi::settings()->get('mjwshared_disablerecordrefund') === FALSE) {
        return;
      }

      foreach ($links as $linkID => $link) {
        if (!empty($link['qs']['is_refund'])) {
          unset($links[$linkID]);
        }
      }

      break;

    case 'Payment':
      if ($op !== 'Payment.edit.action') {
        return;
      }
      if ((boolean)\Civi::settings()->get('mjwshared_refundpaymentui') === FALSE) {
        return;
      }

      try {
        $contribution = reset(civicrm_api3('Mjwpayment', 'get_contribution', [
          'payment_id' => $values['id'],
          'contribution_test' => ['IS NOT NULL' => 1],
        ])['values']);
        // Don't allow refunds if contribution status is "Refunded"
        if ((int)$contribution['contribution_status_id'] === CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Refunded')) {
          return;
        }
        $payment = $contribution['payments'][$values['id']];
        // Don't allow refunds if payment status is not "Completed"
        if ((int)$payment['status_id'] !== CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed')) {
          return;
        }
        // Don't allow refunds if we have no trxn_id to match it against.
        if (empty($payment['trxn_id'])) {
          return;
        }
        if ($payment['total_amount'] < 0) {
          return;
        }
        $paymentProcessor = \Civi\Payment\System::singleton()
          ->getById($payment['payment_processor_id']);
        if (isset($paymentProcessor) && $paymentProcessor->supportsRefund()) {
          // Add the refund link to the payment
          $links[] = [
            'name' => 'Refund Payment',
            'icon' => 'fa-undo',
            'url' => 'civicrm/mjwpayment/refund',
            'class' => 'medium-popup',
            'qs' => 'reset=1&payment_id=%%id%%&contribution_id=%%contribution_id%%',
            'title' => 'Refund Payment',
          ];
        }
      }
      catch (Exception $e) {
        // Do nothing. We just don't add the "refund" link.
      }

      break;
  }
}

/**
 * Implements hook_civicrm_alterLogTables().
 *
 * Exclude tables from logging tables since they hold mostly temp data.
 */
function mjwshared_civicrm_alterLogTables(&$logTableSpec) {
  unset($logTableSpec['civicrm_paymentprocessor_webhook']);
}

/**
 * Add stripe.js to forms, to generate stripe token
 * hook_civicrm_alterContent is not called for all forms (eg. CRM_Contribute_Form_Contribution on backend)
 *
 * @param string $formName
 * @param \CRM_Core_Form $form
 *
 * @throws \CRM_Core_Exception
 */
function mjwshared_civicrm_buildForm($formName, &$form) {
  // Don't load js on ajax forms
  if (CRM_Utils_Request::retrieveValue('snippet', 'String') === 'json') {
    return;
  }

  // On Wordpress frontend we may have a different basePage (eg. mysite.com/mycrm/contribute/transact)
  // CRM.payment.isAJAXPaymentForm requires the basePage to compare URLs.
  $basePage = 'civicrm';
  if (CRM_Core_Config::singleton()->userFramework === 'WordPress') {
    $wpBasePage = \Civi::settings()->get('wpBasePage');
    if (!empty($wpBasePage)) {
      $basePage = $wpBasePage;
    }
  }

  $jsVars = [
    'jsDebug' => (boolean) \Civi::settings()->get('mjwshared_jsdebug'),
    'basePage' => $basePage,
  ];

  \Civi::resources()->addVars('payment', $jsVars);

  // CMS-specific handling
  if (in_array(CRM_Core_Config::singleton()->userFramework, ['Drupal', 'Drupal8'])) {
    // Assign to smarty so we can add via Card.tpl for drupal webform because addVars doesn't work in that context
    // Required in Drupal7. Not sure if required in Drupal8/9.
    $form->assign('paymentJSVars', $jsVars);
    CRM_Core_Region::instance('billing-block')->add(
      ['template' => 'CRM/Mjwshared/Form/DrupalWebformBillingBlock.tpl', 'weight' => -1]);
  }
}

/**
 * @param \Civi\Core\DAO\Event\PreUpdate $event
 */
function mjwshared_symfony_preUpdateInsert(\Civi\Core\DAO\Event\PreUpdate $event) {
  if ($event->object instanceof CRM_Contribute_BAO_ContributionRecur) {
    // Handle deprecated civicrm_contribution_recur.trxn_id and set it to match processor_id if empty
    if (!empty($event->object->processor_id) && empty($event->object->trxn_id)) {
      // We set trxn_id to match processor_id as it is still used in some places
      $event->object->trxn_id = $event->object->processor_id;
    }
    elseif (!empty($event->object->trxn_id) && empty($event->object->processor_id)) {
      // warn old set
      CRM_Core_Error::deprecatedWarning('Payment processor needs updating to use civicrm_contribution_recur.processor_id instead of deprecated civicrm_contribution_recur.trxn_id');
      $event->object->processor_id = $event->object->trxn_id;
    }
    if (!empty($event->object->trxn_id) && !empty($event->object->processor_id) && ($event->object->trxn_id !== $event->object->processor_id)) {
      // Warn set to different values
      CRM_Core_Error::deprecatedWarning("Recur ID: {$event->object->id}; civicrm_contribution_recur processor_id is different to trxn_id. trxn_id is deprecated and should be empty or match processor_id");
    }
  }
}
