<?php

use Civi\Api4\Event;
use Civi\Api4\PriceFieldValue;
use CRM_AdvancedEvents_ExtensionUtil as E;

class CRM_AdvancedEvents_AdditionalParticipant {

  /**
   * @param CRM_Event_Form_Registration_AdditionalParticipant $form
   *
   */
  public static function hideSkipParticipantButton(&$form) {
    // Remove the "Skip Participant" option
    try {
      $eventID = $form->getEventID();
      if (empty($eventID)) {
        \Civi::log()->debug('AdvancedEvents: Hide Skip Participant Button event ID is empty!');
        return;
      }
      $hideSkipParticipant = Event::get(FALSE)
        ->addSelect('Advanced_Event_Settings.Hide_Skip_Participant_button')
        ->addWhere('id', '=', $eventID)
        ->execute()
        ->first()['Advanced_Event_Settings.Hide_Skip_Participant_button'];
      if ($hideSkipParticipant) {
        $buttonsGroup = $form->getElement('buttons');
        $buttonElements = $buttonsGroup->getElements();
        foreach ($buttonElements as $id => $element) {
          if ($element->getName() === '_qf_' . $form->getName() . '_next_skip') {
            unset($buttonElements[$id]);
          }
        }
        $buttonsGroup->setElements($buttonElements);
      }
    }
    catch (Exception $e) {}
  }

  /**
   * @param CRM_Core_Form $form
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function addPriceFieldValueVisibilityOption(&$form) {
    $form->add('select', 'participant_visibility', E::ts('Participant Visibility'), [
      'always' => E::ts('Always'),
      'mainparticipant' => E::ts('Main Participant Only'),
      'additionalparticipant' => E::ts('Additional Participant Only'),
    ]);
    CRM_Core_Region::instance('page-body')->add(['template' => 'CRM/AdvancedEvents/PriceFieldValue.tpl']);

    $priceFieldValue = PriceFieldValue::get(FALSE)
      ->addSelect('custom.*')
      ->addWhere('id', '=', $form->getVar('_oid'))
      ->execute()
      ->first();

    $form->setDefaults(['participant_visibility' => $priceFieldValue['Event_options.Participant_Visibility']]);
  }

  /**
   * @param CRM_Core_Form $form
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function savePriceFieldValueVisibilityOption(&$form) {
    $priceFieldValueID = $form->getVar('_oid');
    $participantVisibility = $form->getSubmittedValue('participant_visibility');
    if (!empty($priceFieldValueID) && !empty($participantVisibility)) {
      PriceFieldValue::update(FALSE)
        ->addWhere('id', '=', $priceFieldValueID)
        ->addValue('Event_options.Participant_Visibility', $participantVisibility)
        ->execute();
    }
  }

  /**
   * @param string $pageType
   * @param \CRM_Event_Form_Registration_AdditionalParticipant $form
   * @param $amount
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function filterPriceFieldValueVisibility($pageType, &$form, &$amount) {
    if ($form->isSubmitted()) {
      return;
    }
    $eventID = $form->getEventID();
    if (empty($eventID)) {
      // Not an event page
      return;
    }
    $priceSetID = $form->getPriceSetID();
    if (empty($priceSetID)) {
      // No price set
      return;
    }
    if ($pageType !== 'event') {
      return;
    }

    $priceFieldValues = PriceFieldValue::get(FALSE)
      ->addSelect('custom.*')
      ->addWhere('price_field_id.price_set_id', '=', $priceSetID)
      ->execute()
      ->indexBy('id');

    $priceFieldBlock = &$amount;
    if (!is_array($priceFieldBlock) || empty($priceFieldBlock)) {
      return;
    }

    $isAdditionaParticipantPage = FALSE;
    if ($form instanceof CRM_Event_Form_Registration_AdditionalParticipant) {
      $isAdditionaParticipantPage = TRUE;
    }

    foreach ($priceFieldBlock as &$fee) {
      if (!is_array($fee['options'])) {
        continue;
      }
      foreach ($fee['options'] as $priceFieldValueID => &$priceFieldValue) {
        if ($isAdditionaParticipantPage && ($priceFieldValues[$priceFieldValueID]['Event_options.Participant_Visibility'] === 'mainparticipant')) {
          // Remove price options which should only be displayed for main participant
          unset($fee['options'][$priceFieldValueID]);
        }
        if (!$isAdditionaParticipantPage && ($priceFieldValues[$priceFieldValueID]['Event_options.Participant_Visibility'] === 'additionalparticipant')) {
          // Remove price options which should only be displayed for additional participants
          unset($fee['options'][$priceFieldValueID]);
        }
      }
    }
    $form->_priceSet['fields'] = $priceFieldBlock;
  }

}
