<?php

use Civi\ActionSchedule\RecipientBuilder;

class CRM_CilbExamRegistration_Event_ActionMapping_ByExpiryDate extends CRM_Event_ActionMapping {

/**
   * Unique mapping id. New mappings should return a string to avoid colliding
   * with legacy integer IDs.
   */
  public function getId() {
    return 'event_expiry_date';
  }

  public function getName(): string {
    return 'event_expiry_date';
  }

  public function getLabel(): string {
    return ts('Event Type (with Registration Expiry Date)');
  }

  public function getValueLabels(): array {
    return CRM_Event_PseudoConstant::eventType();
  }

  public function checkAccess(array $entityValue): bool {
    return FALSE;
  }

  /**
   * Merge parent date fields with your custom date field.
   *
   * @param array|null $entityValue
   * @return array
   */
  public function getDateFields(?array $entityValue = NULL): array {
    return [
      'start_date' => ts('Event Start'),
      'end_date' => ts('Event End'),
      'registration_start_date' => ts('Registration Start'),
      'registration_end_date' => ts('Registration End'),
      'registration_expiry_date_93' => ts('Registration Expiry Date'),
    ];
  }

  public function createQuery($schedule, $phase, $defaultParams): CRM_Utils_SQL_Select {
    $selectedValues = (array) \CRM_Utils_Array::explodePadded($schedule->entity_value);
    $selectedStatuses = (array) \CRM_Utils_Array::explodePadded($schedule->entity_status);

    $query = \CRM_Utils_SQL_Select::from('civicrm_participant e')->param($defaultParams);
    $query['casAddlCheckFrom'] = 'civicrm_event r';
    $query['casContactIdField'] = 'e.contact_id';
    $query['casEntityIdField'] = 'e.id';
    $query['casContactTableAlias'] = NULL;

    if (!empty($schedule->start_action_date)) {
      $query['casDateField'] = $schedule->start_action_date == 'registration_expiry_date_93' ? str_replace('event_', 'd.', $schedule->start_action_date) : str_replace('event_', 'r.', $schedule->start_action_date);
    }
    if (empty($query['casDateField']) && $schedule->absolute_date) {
      $query['casDateField'] = "'" . CRM_Utils_Type::escape($schedule->absolute_date, 'String') . "'";
    }

    $query->join('r', 'INNER JOIN civicrm_event r ON e.event_id = r.id');
    $query->join('d', 'INNER JOIN civicrm_value_candidate_res_9 d ON e.id = d.entity_id');
    if ($schedule->recipient_listing && $schedule->limit_to == 1) {
      switch ($schedule->recipient) {
        case 'participant_role':
          $regex = "([[:cntrl:]]|^)" . implode('([[:cntrl:]]|$)|([[:cntrl:]]|^)', \CRM_Utils_Array::explodePadded($schedule->recipient_listing)) . "([[:cntrl:]]|$)";
          $query->where("e.role_id REGEXP (@regex)")
            ->param('regex', $regex);
          break;

        default:
          break;
      }
    }

    // build where clause
    // FIXME: This handles scheduled reminder of type "Event Name" and "Event Type", gives incorrect result on "Event Template".
    if (!empty($selectedValues)) {
      $query->where("r.event_type_id IN (@selectedValues)")
        ->param('selectedValues', $selectedValues);
    }
    else {
      $query->where("r.event_type_id IS NULL");
    }

    $query->where('r.is_active = 1');
    $query->where('r.is_template = 0');

    // participant status criteria not to be implemented for additional recipients
    // ... why not?
    if (!empty($selectedStatuses)) {
      switch ($phase) {
        case RecipientBuilder::PHASE_RELATION_FIRST:
        case RecipientBuilder::PHASE_RELATION_REPEAT:
          $query->where("e.status_id IN (#selectedStatuses)")
            ->param('selectedStatuses', $selectedStatuses);
          break;

      }
    }
    return $query;
  }
}
