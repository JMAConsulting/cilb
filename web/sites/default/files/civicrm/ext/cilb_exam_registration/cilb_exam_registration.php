<?php

require_once 'cilb_exam_registration.civix.php';

use CRM_CilbExamRegistration_ExtensionUtil as E;
use Drupal\user\Entity\User;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function cilb_exam_registration_civicrm_config(&$config): void {
  _cilb_exam_registration_civix_civicrm_config($config);
  // This hook sometimes runs twice
  if (isset(Civi::$statics[__FUNCTION__])) {
    return;
  }
  Civi::$statics[__FUNCTION__] = 1;
  Civi::dispatcher()->addListener('hook_civicrm_pre', '_cilb_exam_registration_external_identifier_set');
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function cilb_exam_registration_civicrm_install(): void {
  _cilb_exam_registration_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function cilb_exam_registration_civicrm_enable(): void {
  _cilb_exam_registration_civix_civicrm_enable();
}

function cilb_exam_registration_civicrm_tabset($tabsetName, &$tabs, $context) {
  // Only modify contact view tabs.
  if ($tabsetName !== 'civicrm/contact/view') {
    return;
  }

  // Get current Drupal user.
  $current_user = \Drupal::currentUser();
  $roles = $current_user->getRoles();

  $has_staff_role = $current_user->hasRole('staff');

  // Check if user has staff role.
  if ($has_staff_role) {
    foreach ($tabs as $key => $tab) {
      if (in_array($tab['id'] ?? '', ['group'])) {
        unset($tabs[$key]);
      }
    }
  }
  foreach ($tabs as $key => $tab) {
    if (in_array($tab['id'] ?? '', ['tag', 'rel'])) {
      unset($tabs[$key]);
    }
  }
  $desired_order = [
    'summary',    // 1. Summary (weight 0)
    'participant', // 2. Exams (weight 10)
    'log',        // 3. Change Log (weight 20)
    'payments1',  // 4. Payments (weight 30)
    'activity',   // 5. Activities (weight 40)
    'note',       // 6. Notes (weight 50)
    'group'       // 7. Groups (weight 60)
  ];

  foreach ($tabs as $key => $tab) {
    $tab_id = $tab['id'] ?? '';

    // Set exact weights for desired order
    $new_weights = [
      'summary'    => 0,
      'participant' => 10,
      'log'        => 20,
      'payments1'  => 30,
      'activity'   => 40,
      'note'       => 50,
      'group'      => 60
    ];

    if (isset($new_weights[$tab_id])) {
      $tabs[$key]['weight'] = $new_weights[$tab_id];
    }
  }
}

function cilb_exam_registration_civicrm_custom($op, $groupID, $entityID, &$params) {
  if (!in_array($op, ['create', 'edit'])) {
    return;
  }

  // ── SSN formatting (group 1 only) ─────────────────────────────────────────
  if ($groupID === 1) {
    $ssnCol    = 'ssn_5';
    $last4Col  = 'ssn_last_4_95';
    $tableName = 'civicrm_value_registrant_in_1';

    foreach ($params as &$field) {
      if ($field['column_name'] === $ssnCol) {
        $rawSsn     = (string) $field['value'];
        $digitsOnly = preg_replace('/\D/', '', $rawSsn);

        if (strlen($digitsOnly) === 9) {
          $formattedSsn = substr($digitsOnly, 0, 3) . '-' .
                          substr($digitsOnly, 3, 2) . '-' .
                          substr($digitsOnly, 5, 4);
          $sql = "UPDATE {$tableName} SET {$ssnCol} = %1 WHERE entity_id = %2";
          CRM_Core_DAO::executeQuery($sql, [
            1 => [$formattedSsn, 'String'],
            2 => [$entityID, 'Integer'],
          ]);
        }
        else {
          $field['value'] = $digitsOnly;
        }

        $last4 = strlen($digitsOnly) >= 4 ? substr($digitsOnly, -4) : '';
        add_update_ssn_last_4($tableName, $entityID, $ssnCol, $last4Col, $field['value'], $last4);
        break;
      }
    }
    unset($field);
  }

  // ── Suppress Candidate_Number default when event changed ──────────────────
  if (empty(\Civi::$statics['cilb_exam_registration']['suppress_candidate_number'][$entityID])) {
    return;
  }

  $fieldMeta = _cilb_exam_registration_candidate_number_meta();
  if (!$fieldMeta) {
    return;
  }

  $nulled = FALSE;
  foreach ($params as &$param) {
    $colName = is_object($param)
      ? ($param->column_name ?? '')
      : ($param['column_name'] ?? '');

    if ($colName === $fieldMeta['column']) {
      is_object($param) ? ($param->value = NULL) : ($param['value'] = NULL);
      $nulled = TRUE;
      break;
    }
  }
  unset($param);

  if ($nulled) {
    \Civi::log()->info(
      'Suppressed default Candidate_Number write for participant {id}',
      ['id' => $entityID]
    );
  }

}

/**
 * Helper function to handle INSERT/UPDATE safely
 */
function add_update_ssn_last_4($tableName, $entityID, $ssnCol, $last4Col, $ssnValue, $last4Value) {
  // Check if record exists
  $existsSql = "SELECT id FROM {$tableName} WHERE entity_id = %1";
  $existsDao = CRM_Core_DAO::executeQuery($existsSql, [1 => [$entityID, 'Integer']]);

  if ($existsDao->fetch()) {
    // UPDATE existing record
    $sql = "UPDATE {$tableName} SET {$last4Col} = %1 WHERE entity_id = %2";
    CRM_Core_DAO::executeQuery($sql, [
      1 => [$last4Value, 'String'],
      2 => [$entityID, 'Integer']
    ]);
  } else {
    // INSERT new record with all required fields
    $sql = "INSERT INTO {$tableName} (entity_id, {$ssnCol}, {$last4Col}) VALUES (%1, %2, %3)";
    CRM_Core_DAO::executeQuery($sql, [
      1 => [$entityID, 'Integer'],
      2 => [$ssnValue, 'String'],
      3 => [$last4Value, 'String']
    ]);
  }
}

function cilb_exam_registration_civicrm_pre(string $op, string $objectName, ?int $id, array &$params): void {
  if ($objectName !== 'Participant' || $op !== 'edit' || empty($id)) {
    return;
  }

  // Don't snapshot during our own internal blanking update.
  if (!empty(\Civi::$statics['cilb_exam_registration']['blanking'][$id])) {
    return;
  }

  try {
    $participant = \Civi\Api4\Participant::get(FALSE)
      ->addSelect('event_id')
      ->addWhere('id', '=', $id)
      ->execute()
      ->first();

    if (!$participant) {
      return;
    }

    $oldEventId = (int) $participant['event_id'];
    \Civi::$statics['cilb_exam_registration']['pre_event'][$id] = $oldEventId;

    // If the incoming $params already carry the new event_id we can set the
    // suppress flag immediately so hook_civicrm_custom sees it in time.
    if (!empty($params['event_id']) && (int) $params['event_id'] !== $oldEventId) {
      \Civi::$statics['cilb_exam_registration']['suppress_candidate_number'][$id] = TRUE;
    }
  }
  catch (\Exception $e) {
    \Civi::log()->warning('cilb_exam_registration pre hook: could not read event_id for participant {id}', [
      'id'  => $id,
      'msg' => $e->getMessage(),
    ]);
  }
}

function cilb_exam_registration_civicrm_post(string $op, string $objectName, ?int $objectId, &$objectRef): void {
  if ($objectName !== 'Participant' || $op !== 'edit' || empty($objectId)) {
    return;
  }

  if (!empty(\Civi::$statics['cilb_exam_registration']['blanking'][$objectId])) {
    return;
  }

  $preEventId = \Civi::$statics['cilb_exam_registration']['pre_event'][$objectId] ?? NULL;
  unset(\Civi::$statics['cilb_exam_registration']['pre_event'][$objectId]);

  // Clear the suppress flag NOW — before any further writes — so
  // hook_civicrm_custom doesn't fire again and swallow our blank.
  unset(\Civi::$statics['cilb_exam_registration']['suppress_candidate_number'][$objectId]);

  if ($preEventId === NULL) {
    return;
  }

  $newEventId = isset($objectRef->event_id) ? (int) $objectRef->event_id : NULL;

  if ($newEventId === NULL) {
    try {
      $row = \Civi\Api4\Participant::get(FALSE)
        ->addSelect('event_id')
        ->addWhere('id', '=', $objectId)
        ->execute()
        ->first();
      $newEventId = $row ? (int) $row['event_id'] : NULL;
    }
    catch (\Exception $e) {
      \Civi::log()->warning('Could not re-fetch event_id for participant {id}', [
        'id'  => $objectId,
        'msg' => $e->getMessage(),
      ]);
      return;
    }
  }

  if ($preEventId === $newEventId) {
    return;
  }

  \Civi::$statics['cilb_exam_registration']['blanking'][$objectId] = TRUE;

  try {
    // Resolve the custom table/column via the schema — cached after first call.
    $fieldMeta = _cilb_exam_registration_candidate_number_meta();

    if ($fieldMeta) {
      // Direct SQL guarantees the NULL is written regardless of how CiviCRM
      // handles NULL values passed through the API or custom hook $params.
      CRM_Core_DAO::executeQuery(
        "UPDATE `{$fieldMeta['table']}` SET `{$fieldMeta['column']}` = NULL WHERE entity_id = %1",
        [1 => [$objectId, 'Integer']]
      );

      \Civi::log()->info(
        'Blanked Candidate_Number for participant {id} (event {old} → {new})',
        ['id' => $objectId, 'old' => $preEventId, 'new' => $newEventId]
      );
    }
    else {
      \Civi::log()->error('Could not resolve Candidate_Number table/column — blank skipped for participant {id}', [
        'id' => $objectId,
      ]);
    }
  }
  catch (\Exception $e) {
    \Civi::log()->error('Failed to blank Candidate_Number for participant {id}: {msg}', [
      'id'  => $objectId,
      'old' => $preEventId,
      'new' => $newEventId,
      'msg' => $e->getMessage(),
    ]);
  }
  finally {
    unset(\Civi::$statics['cilb_exam_registration']['blanking'][$objectId]);
  }
}

/**
 * Resolves and caches the physical table name and column name for
 * Candidate_Result.Candidate_Number so we only hit the DB once per request.
 *
 * Returns ['table' => '...', 'column' => '...'] or NULL on failure.
 */
function _cilb_exam_registration_candidate_number_meta(): ?array {
  $statics = &\Civi::$statics['cilb_exam_registration'];

  if (array_key_exists('candidate_number_meta', $statics ?? [])) {
    return $statics['candidate_number_meta'];
  }

  try {
    $field = \Civi\Api4\CustomField::get(FALSE)
      ->addSelect('column_name', 'custom_group_id.table_name')
      ->addWhere('custom_group_id:name', '=', 'Candidate_Result')
      ->addWhere('name', '=', 'Candidate_Number')
      ->execute()
      ->first();

    $statics['candidate_number_meta'] = $field ? [
      'table'  => $field['custom_group_id.table_name'],
      'column' => $field['column_name'],
    ] : NULL;
  }
  catch (\Exception $e) {
    \Civi::log()->error('Could not resolve Candidate_Number meta: {msg}', [
      'msg' => $e->getMessage(),
    ]);
    $statics['candidate_number_meta'] = NULL;
  }

  return $statics['candidate_number_meta'];
}

function cilb_exam_registration_civicrm_postProcess($formName, $form) {
  if ($formName == 'CRM_Contact_Form_Inline_CustomData') {
    $params = $form->getSubmittedValues();
    $cfID = \Civi\Api4\CustomField::get(FALSE)
                  ->addWhere('name', '=', 'Exam_Language_Preference')
                  ->addWhere('custom_group_id:name', '=', 'Registrant_Info')
                  ->execute()->first()['id'];
    foreach ($params as $key => $value) {
      if ($cfID == CRM_Core_BAO_CustomField::getKeyID($key)) {
        $contactID = $form->getContactID();
        if ($ufID = CRM_Core_BAO_UFMatch::getUFId($contactID)) {
          $user = \Drupal::currentUser()->isAuthenticated() ? User::load($ufID) : NULL;
          $mapper = [1 => 'en', 2 => 'es'];
          $user?->set('preferred_langcode', ($mapper[$value] ?? 'en'))->save();
        }
      }
    }
  }
}


function cilb_exam_registration_civicrm_alterMailParams(&$params, $context) {
   if (in_array($params['valueName'], ['contribution_online_receipt', 'contribution_invoice_receipt'])) {
     $participants = \Civi\Api4\Participant::get(FALSE)
       ->addSelect('event_id.Exam_Details.Exam_Part:label', 'event_id.event_type_id:label', 'event_id.title')
       ->addWhere('Participant_Webform.Candidate_Payment', '=', $params['tplParams']['contributionID'])
       ->addWhere('contact_id', '=', $params['contactId'])
       ->execute()
       ->indexBy('id');
     $events = [];
     foreach ($participants as $participant) {
       $events[$participant['id']] = [
         'exam_part' => $participant['event_id.Exam_Details.Exam_Part:label'],
         'exam_category' => ($participant['event_id.event_type_id:label'] === 'Business and Finance') ? '' : $participant['event_id.event_type_id:label'],
       ];
     }
     $params['tplParams']['events'] = $events;
     $lineItems = CRM_Price_BAO_LineItem::getLineItemsByContributionID($params['tplParams']['contributionID']);
     foreach ($lineItems as $k => $lineItem) {
       if ($lineItem['entity_table'] == 'civicrm_participant') {
          $lineItems[$k]['title'] = $participants[$lineItem['entity_id']]['event_id.title'] . ' - ' . $lineItem['field_title'];
       }
       else {
         $lineItems[$k]['title'] = $lineItems[$k]['label'];
       }
     }
     $params['tplParams']['lineItems'] = $lineItems;
   }
}

function _cilb_exam_registration_external_identifier_set(\Civi\Core\Event\PreEvent $event) {
  if ($event->action == 'edit' && \Civi\Api4\Utils\CoreUtil::isContact($event->entity)) {
    $params = $event->params;
    $contactID = $params['id'] ?? $params['contact_id'] ?? NULL;
    if ($contactID) {
      if ($ufID = CRM_Core_BAO_UFMatch::getUFId($contactID)) {
        $user = \Drupal::currentUser()->isAuthenticated() ? User::load($ufID) : NULL;
        if (!empty($params['preferred_language'])) {
          $langcode = strstr($params['preferred_language'], 'es_') ? 'es' : 'en';
          $user?->set('preferred_langcode', $langcode)->save();
        }
        elseif (!empty($params['custom'])) {
          $cfID = \Civi\Api4\CustomField::get(FALSE)
                  ->addWhere('name', '=', 'Exam_Language_Preference')
                  ->addWhere('custom_group_id:name', '=', 'Registrant_Info')
                  ->execute()->first()['id'];
          foreach ($params['custom'] as $id => $value) {
            foreach ($value as $data) {
              if (!empty($data['custom_field_id'] == $cfID)) {
                $mapper = [1 => 'en', 2 => 'es'];
                $user = \Drupal::currentUser()->isAuthenticated() ? User::load($ufID) : NULL;
                $user?->set('preferred_langcode', $mapper[$data['value']])->save();
              }
            }
          }
        }
      }
    }
  }
  if ($event->action == 'create' && \Civi\Api4\Utils\CoreUtil::isContact($event->entity)) {
    $params = $event->params;
    if (empty($params['external_identifier'])) {
      $current_max_external_identifier = CRM_Core_DAO::singleValueQuery("SELECT MAX(external_identifier) FROM civicrm_contact");
      $current_max_external_identifier = (float) $current_max_external_identifier;
      $params['external_identifier'] = $current_max_external_identifier + 1;
      $uniquenessCheck = CRM_Core_DAO::singleValueQuery("SELECT count(id) FROM civicrm_contact WHERE external_identifier = %1", [
        1 => [$params['external_identifier'], 'String'],
      ]);
      while (!empty($uniquenessCheck)) {
        $params['external_identifier'] = $params['external_identifier'] + 1;
        $uniquenessCheck = CRM_Core_DAO::singleValueQuery("SELECT count(id) FROM civicrm_contact WHERE external_identifier = %1", [
          1 => [$params['external_identifier'], 'String'],
        ]);
      }
    }
    $event->params = $params;
  }
}
