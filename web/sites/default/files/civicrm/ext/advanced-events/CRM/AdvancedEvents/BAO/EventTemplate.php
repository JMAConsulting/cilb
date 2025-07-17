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

use Civi\Api4\Event;
use Civi\Api4\EventTemplate;
use CRM_AdvancedEvents_ExtensionUtil as E;

class CRM_AdvancedEvents_BAO_EventTemplate extends CRM_AdvancedEvents_DAO_EventTemplate {

  /**
   * @param $templateId
   * @param $params
   *
   * @return bool
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function eventAlreadyExists($templateId, $params) {
    $existingEventIDs = EventTemplate::get(FALSE)
      ->addWhere('template_id', '=', $templateId)
      ->execute()
      ->column('event_id');
    $events = \Civi\Api4\Event::get(FALSE)
      ->addWhere('id', 'IN', $existingEventIDs)
      ->addWhere('start_date', '=', $params['start_date'])
      ->execute();
    if ($events->count()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function getEventTemplatesPseudoConstant(): array {
    return Event::get(TRUE)
      ->addWhere('is_template', '=', TRUE)
      ->addOrderBy('template_title', 'ASC')
      ->execute()
      ->indexBy('id')
      ->column('template_title');
  }

}
