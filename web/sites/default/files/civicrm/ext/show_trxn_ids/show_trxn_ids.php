<?php

require_once 'show_trxn_ids.civix.php';

use CRM_ShowTrxnIds_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function show_trxn_ids_civicrm_config(&$config): void
{
  _show_trxn_ids_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function show_trxn_ids_civicrm_install(): void
{
  _show_trxn_ids_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function show_trxn_ids_civicrm_enable(): void
{
  _show_trxn_ids_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_searchColumns/
 */
function show_trxn_ids_civicrm_searchColumns($objectName, &$headers,  &$values, &$selector): void
{
  if ($objectName == 'contribution') {
    foreach ($headers as $_ => $header) {
      // \Civi::log()->debug(print_r($header['name'], TRUE) . $header['weight']);
      if (!empty($header['name']) && $header['name'] == 'Amount') {
        //NOTE: the contribution amount is hardcoded as the first column even through the weight is different - see templates/CRM/Contribute/Form/Selector.tpl:47
        // As such the trxn id is to the right of Amount
        $weight = $header['weight'] + 5;
        $headers[] = [
          'name' => E::ts('Transaction ID'),
          'field_name' => 'trxn_id',
          'type' => null,
          'weight' => $weight,
        ];
        foreach ($values as $key => $value) {
          // \Civi::log()->debug(print_r($value, TRUE));
          $contribution = \Civi\Api4\Contribution::get(FALSE)
            ->addWhere('id', '=', $value['contribution_id'])
            ->addSelect('trxn_id')
            ->execute();
          if (!empty($contribution)) {
            $trxnId = $contribution[0]['trxn_id'];
          }
          $values[$key]['trxn_id'] = $trxnId;
          // \Civi::log()->debug(print_r($values[$key], TRUE));
        }
        break;
      }
    }
  }
  /*if ($objectName == 'event') {
	  CRM_Core_Error::debug('hader', $headers);
    //TODO: use another hook for Contact Summary > Events as the search doesn't support this hook
    foreach ($headers as $_ => $header) {
      // \Civi::log()->debug($header['name'] . "\n");
      // \Civi::log()->debug(print_r($header, TRUE));
      if (!empty($header['name'] && $header['name'] == 'Exam')) {
        $weight = $header['weight'] + 5;
        $headers[] = [
          'name' => E::ts('Transaction ID'),
          'sort' => 'trxn_id',
          'direction' => 4,
        ];
        foreach ($values as $key => $value) {
          // \Civi::log()->debug(print_r($value, TRUE));
          $result = civicrm_api3('ParticipantPayment', 'get', [
            'sequential' => 1,
            'return' => ["contribution_id.trxn_id"],
            'participant_id' => $value['participant_id']
          ]);
          if (!empty($result['values'])) {
            $trxnId = $result['values'][0]['contribution_id.trxn_id'];
          }
          $values[$key]['trxn_id'] = $trxnId;
          // \Civi::log()->debug(print_r($values[$key], TRUE));
        }
        break;
      }
    }
  }
  // foreach ($headers as $i => $header) {
  //   \Civi::log()->debug($header['name'] . $header['weight'] . print_r($header, TRUE));
  // }
   */
  if ($objectName == 'event') {
    // Find the index of the column after which you want to insert Transaction ID
    $insertAfter = -1;
    foreach ($headers as $idx => $header) {
      if (!empty($header['name']) && $header['name'] == 'Exam') {
        $insertAfter = $idx;
        break;
      }
    }

    if ($insertAfter >= 0) {
      // Prepare the new header
      $newHeader = [
        'name' => 'Transaction ID',
        'title' => 'Transaction ID',
        'sort' => 'trxn_id',
        'direction' => 4, // Direction is rarely needed; remove if not used
      ];

      // Insert the new header at the desired position
      array_splice($headers, $insertAfter + 1, 0, [$newHeader]);

      // Add trxn_id to each value row
      foreach ($values as $key => $value) {
        $trxnId = '';
        if (!empty($value['participant_id'])) {
          $result = civicrm_api3('ParticipantPayment', 'get', [
            'sequential' => 1,
            'return' => ["contribution_id.trxn_id"],
            'participant_id' => $value['participant_id'],
          ]);
          if (!empty($result['values'][0]['contribution_id.trxn_id'])) {
            $trxnId = $result['values'][0]['contribution_id.trxn_id'];
          }
        }
        $values[$key]['trxn_id'] = $trxnId;
      }
    }
  }
}

function show_trxn_ids_civicrm_buildForm($formName, &$form)
{
  if ($formName == 'CRM_Event_Form_Search') {
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => __DIR__ . '/templates/trxn_column.tpl',
    ));
  }
  if ($formName != 'CRM_Event_Form_ParticipantView' && $formName != 'CRM_Contribute_Form_ContributionView') {
    return;
  }
  if ($formName == 'CRM_Contribute_Form_ContributionView') {
    $contactClass = 'crm-contribution-form-block-contact_id';
    $contributionId = $form->getContributionID();
    $trxnId = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('id', '=', $contributionId)
      ->addSelect('trxn_id')
      ->execute()[0]['trxn_id'];
  }
  if ($formName == 'CRM_Event_Form_ParticipantView') {
    $contactClass = 'crm-event-participantview-form-block-displayName';
    $participantId = $form->getParticipantID();
    $result = civicrm_api3('ParticipantPayment', 'get', [
      'sequential' => 1,
      'return' => ["contribution_id.trxn_id"],
      'participant_id' => $participantId,
    ]);
    $trxnId = $result['values'][0]['contribution_id.trxn_id'];
  }
  $form->assign('trxn_id', $trxnId);
  $form->assign('contact_class', $contactClass);
  CRM_Core_Region::instance('page-body')->add(array(
    'template' => __DIR__ . '/templates/transaction_id.tpl',
  ));
}
