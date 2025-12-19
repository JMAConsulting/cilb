<?php

require_once 'advanced_events.civix.php';
use CRM_AdvancedEvents_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function advanced_events_civicrm_config(&$config) {
  _advanced_events_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function advanced_events_civicrm_install() {
  _advanced_events_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function advanced_events_civicrm_enable() {
  _advanced_events_civix_civicrm_enable();
}

function advanced_events_civicrm_tabset($tabsetName, &$tabs, $context) {
  //check if the tab set is Event manage
  if ($tabsetName == 'civicrm/event/manage') {
    foreach (CRM_AdvancedEvents_Functions::getEnabled() as $functionName => $enabled) {
      if (empty($enabled)) {
        unset($tabs[$functionName]);
      }
    }
    if (empty($context['event_id'])) {
      // We are on the "Manage Events" page - disable repeat link
      unset($tabs['repeat']);
    }
    if (!empty($context['event_id'])) {
      $eventTemplate = \Civi\Api4\Event::get(FALSE)
        ->addSelect('id')
        ->addWhere('is_template', '=', TRUE)
        ->addWhere('id', '=', $context['event_id'])
        ->execute()
        ->first();
      if (!empty($eventTemplate)) {
        // We are on manage event detail and it's a template event - show repeat functions
        $tabs['repeat'] = [
          'title' => 'Repeat',
          'link' => CRM_Utils_System::url('civicrm/admin/advancedevents/repeat', ['action' => 'update', 'id' => $eventTemplate['id'], 'selectedChild' => 'repeat']),
          'valid' => TRUE,
          'active' => TRUE,
          'current' => FALSE,
          'class' => 'livePage',
        ];
        $tabs['linkedevents'] = [
          'title' => 'Manage Linked Events',
          'link' => CRM_Utils_System::url('civicrm/admin/advancedevents/linked', ['template_id' => $eventTemplate['id'], 'selectedChild' => 'linkedevents']),
          'valid' => TRUE,
          'active' => TRUE,
          'current' => FALSE,
          'class' => 'livePage',
        ];
      }
      else {
        // We are on manage event detail and it's not a template event
        unset($tabs['repeat']);
      }
    }
  }
}

function advanced_events_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  switch ($objectName) {
    case 'Event':
      switch ($op) {
        case 'event.manage.list':
          // Add a "Create Template" link
          $links[] = [
            'name' => 'Create Template',
            'title' => 'Create Template from Event',
            'url' => 'civicrm/admin/eventTemplate',
            'qs' => 'reset=1&action=copy&id=%%id%%',
            'extra' => 'onclick = "return confirm(\'Are you sure you want to create a template from this Event?\');"',
            'weight' => 0,
          ];
          break;

      }
      break;

  }
}

function advanced_events_civicrm_pre($op, $objectName, $id, &$params) {
  switch ($objectName) {
    case 'Event':
      switch ($op) {
        case 'create':
          if (!empty($params['template_id']) && empty($params['template_title'])) {
            // This is a new event being created against a template, populate some parameters
            $params['is_template'] = 0;
            $params['template_title'] = '';
            $params['parent_event_id'] = NULL;
          }
        // Fall through to edit so we can make sure the title is set.
        case 'edit':
          // Templates do not get a title, but we need them to have one to use RecurringEntity to create events from them
          if (!empty($params['template_title']) && empty($params['title'])) {
            $params['title'] = $params['template_title'];
          }
          break;
      }
      break;
  }
}

function advanced_events_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  switch ($objectName) {
    case 'Event':
      switch ($op) {
        case 'create':
          // When an event is created via "Add Event" we need to create an EventTemplate record
          $templateId = CRM_Utils_Request::retrieveValue('template_id', 'Positive');
          $eventId = $objectId;
          if (empty($eventId) || empty($templateId)) {
            return;
          }
          $eventTemplateTitle = \Civi\Api4\Event::get(FALSE)
            ->addSelect('template_title')
            ->addWhere('id', '=', $templateId)
            ->execute()
            ->first()['template_title'];
          $params = [
            'event_id' => $objectId,
            'template_id' => $templateId,
            'title' => $eventTemplateTitle,
          ];
          civicrm_api3('EventTemplate', 'create', $params);
          break;
      }
      break;
  }
}

function advanced_events_civicrm_copy($objectName, $objectRef) {
  if ($objectName !== 'Event') {
    return;
  }
  if (empty($objectRef->created_id) || empty($objectRef->created_date)) {
    if (CRM_Core_Transaction::isActive()) {
      CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT, 'advanced_events_civicrm_copy_callback', [$objectRef]);
    }
    else {
      advanced_events_civicrm_copy_callback($objectRef);
    }
  }
}

function advanced_events_civicrm_copy_callback($objectRef) {
  // Core does not fill in the created_id, created_date fields - maybe it should
  $eventParams = [
    'id' => $objectRef->id,
    'created_id' => CRM_Core_Session::getLoggedInContactID(),
    'created_date' => date('YmdHis'),
    'is_template' => $objectRef->is_template,
  ];
  civicrm_api3('Event', 'create', $eventParams);
}

function advanced_events_civicrm_pageRun(&$page) {
  if ($page instanceof CRM_Event_Page_ManageEvent) {
    // Insert a link to the event template
    $method = 'getTemplateVars';
    $rows = $page->$method()['rows'];
    foreach ($rows as $eventId => &$details) {
      if ($eventId === 'tab') { continue; }
      $details['template'] = '';
      // Get the url/details for the template for the event
      if (is_numeric($eventId)) {
        $eventTemplate = \Civi\Api4\EventTemplate::get(FALSE)
          ->addSelect('template_id', 'title')
          ->addWhere('event_id', '=', $eventId)
          ->execute()
          ->first();
        if (!empty($eventTemplate)) {
          $url = CRM_Utils_System::url('civicrm/event/manage/settings', "action=update&id={$eventTemplate['template_id']}&reset=1");
          $details['template'] = "<a class='action-item crm-hover-button' href='{$url}' target=_blank>{$eventTemplate['title']}</a>";
        }
      }
    }
    $page->assign('rows', $rows);
  }
}

/**
 * Intercept form functions
 * @param $formName
 * @param $form
 */
function advanced_events_civicrm_buildForm($formName, &$form) {
  switch ($formName) {
    case 'CRM_Event_Form_ManageEvent_EventInfo':
    case 'CRM_AdvancedEvents_Form_ManageEvent_Linked':
      Civi::resources()->addBundle('bootstrap3');
      break;
  }

  switch ($formName) {
    case 'CRM_AdvancedEvents_Form_ManageEvent_Linked':
      Civi::service('angularjs.loader')->addModules(['afsearchAdvancedEventsLinkedEvents']);
      break;

    case 'CRM_Event_Form_ManageEvent_EventInfo':
      /** @var \CRM_Event_Form_ManageEvent_EventInfo $form */
      if (CRM_Utils_Request::retrieve('action', 'String', $form) == CRM_Core_Action::ADD) {
        if (!CRM_Core_Permission::check('create event')) {
          CRM_Core_Error::statusBounce(E::ts('You do not have permission to create events.'));
        }
      }
      // This is required to save customdata on ManageEvent/EventInfo form.
      // When the form loads it calls civicrm/custom with entityID=eventID
      // If that is NOT set it won't save!
      $form->assign('entityID', $form->getEventID());
      break;

    case 'CRM_Event_Form_Registration_AdditionalParticipant':
      CRM_AdvancedEvents_AdditionalParticipant::hideSkipParticipantButton($form);
      break;

    case 'CRM_Price_Form_Option':
      CRM_AdvancedEvents_AdditionalParticipant::addPriceFieldValueVisibilityOption($form);
      break;
  }
}

function advanced_events_civicrm_postProcess($formName, &$form) {
  if ($formName !== 'CRM_Price_Form_Option') {
    return;
  }
  CRM_AdvancedEvents_AdditionalParticipant::savePriceFieldValueVisibilityOption($form);
}

/**
 * Implements hook_civicrm_entity_supported_info().
 * This allows EventTemplate entity to be used in Drupal Views etc.
 */
function advanced_events_civicrm_entity_supported_info(&$civicrm_entity_info) {
  $civicrm_entity_info['civicrm_event_template'] = [
    'civicrm entity name' => 'event_template', // the api entity name
    'label property' => 'title', // name is the property we want to use for the entity label
    'permissions' => [
      'view' => ['view event info'],
      'edit' => ['edit all events'],
      'update' => ['edit all events'],
      'create' => ['edit all events'],
      'delete' => ['delete in CiviEvent'],
    ],
    'display suite' => [
      'link fields' => [
        ['link_field' => 'event_id', 'target' => 'civicrm_event'],
        ['link_field' => 'template_id', 'target' => 'civicrm_event'],
      ]
    ]
  ];
}

/**
 * Implementation of hook_civicrm_permission
 *
 * @param array $permissions
 * @return void
 */
function advanced_events_civicrm_permission(&$permissions) {
  $permissions['create event'] = [
    'label' => E::ts('CiviEvent: Create Event'),
  ];
  $permissions['view own event templates'] = [
    'label' => E::ts('CiviEvent: View own event templates'),
  ];
  $permissions['view all event templates'] = [
    'label' => E::ts('CiviEvent: View all event templates'),
  ];
  $permissions['edit own event templates'] = [
    'label' => E::ts('CiviEvent: Edit own event templates'),
  ];
  $permissions['edit all event templates'] = [
    'label' => E::ts('CiviEvent: Edit all event templates'),
  ];
  $permissions['delete own event templates'] = [
    'label' => E::ts('CiviEvent: Delete own event templates'),
  ];
  $permissions['delete all event templates'] = [
    'label' => E::ts('CiviEvent: Delete all event templates'),
  ];
}

/**
 * Set the first amount for the membership fee on sign-up
 *  Pro-rata or first amount based on other MembershipType custom fields
 *
 * @param $pageType
 * @param $form
 * @param $amount
 *
 * @throws \CRM_Core_Exception
 */
function advanced_events_civicrm_buildAmount($pageType, &$form, &$amount) {
  $formName = get_class($form);
  switch ($formName) {
    case 'CRM_Event_Form_Registration_Register':
    case 'CRM_Event_Form_Registration_AdditionalParticipant':
      CRM_AdvancedEvents_AdditionalParticipant::filterPriceFieldValueVisibility($pageType, $form, $amount);
      break;
  }

}

function advanced_events_civicrm_searchTasks($objectName, &$tasks) {
  if ($objectName == 'event') {
    if (CRM_Core_Permission::check('administer CiviCRM data') || CRM_Core_Permission::check('administer CiviCRM')) {
      $tasks[] = [
        'title' => E::ts('Copy participants'),
        'class' => 'CRM_AdvancedEvents_Form_Task_CopyParticipants',
        'url' => 'civicrm/task/event-copy-participants',
      ];
    }
  }
}

/**
 * SearchKitTasks hook.
 *
 * @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_searchKitTasks/
 *
 * @param array $tasks
 * @param bool $checkPermissions
 * @param int|null $userID
 */
function advanced_events_civicrm_searchKitTasks(array &$tasks, bool $checkPermissions, ?int $userID, ?array $search = [], ?array $display = []) {
  $task = [
    'title' => E::ts('Copy participants'),
    'class' => 'CRM_AdvancedEvents_Form_Task_CopyParticipants',
    'url' => 'civicrm/task/event-copy-participants',
  ];

  $tasks[] = $task;

  $key = \CRM_Core_Key::get(\CRM_Utils_Array::first((array) $task['class']), TRUE);

  // Print Labels action does not support popups, open full-screen
  $actionType = 'crmPopup';

  $tasks['Event']['event.copyparticipant'] = [
    'title' => $task['title'],
    'icon' => $task['icon'] ?? 'fa-gear',
    $actionType => [
      'path' => "'{$task['url']}'",
      'query' => "{reset: 1}",
      'data' => "{cids: ids.join(','), qfKey: '$key'}",
    ],
  ];
}
