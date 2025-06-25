<?php
namespace Civi\PaymentprocessorWebhook;

use Civi\Core\Service\AutoSubscriber;
use CRM_Mjwshared_ExtensionUtil as E;

class Tasks extends AutoSubscriber {

  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_searchKitTasks' => 'onSearchKitTasks',
    ];
  }

  public function onSearchKitTasks(array &$tasks, bool $checkPermissions, ?int $userID): void {
    $tasks['PaymentprocessorWebhook']['retry'] = [
      'title' => E::ts('Retry Paymentprocessor Webhooks'),
      'icon' => 'fa-rectangle-refresh',
      'apiBatch' => [
        'action' => 'update',
        'params' => ['values' => ['status' => 'new', 'processed_date' => NULL]],
        'confirmMsg' => E::ts('Schedule retry for %1 %2.'),
        'runMsg' => E::ts('Scheduling retry for %1 %2...'),
        'successMsg' => E::ts('%1 %2 have been scheduled for retry (will retry next time scheduled jobs are run).'),
        'errorMsg' => E::ts('An error occurred while attempting to schedule retry for %1 %2.'),
      ],
    ];
  }

}
