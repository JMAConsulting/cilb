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

namespace Civi\Mjwshared;

use Civi\Api4\Domain;
use Civi\Api4\PaymentProcessor;
use Civi\Api4\PaymentprocessorWebhook;
use Civi\Core\Service\AutoSubscriber;
use CRM_Mjwshared_ExtensionUtil as E;

/**
 * Class CRM_Mjwshared_Check
 */
class Check extends AutoSubscriber {

  /**
   * @return string[]
   */
  public static function getSubscribedEvents(): array {
    return [
      '&hook_civicrm_check' => 'checkRequirements',
    ];
  }

  /**
   * @var string
   */
  const MIN_VERSION_SWEETALERT = '1.8';

  /**
   * @var array
   */
  private array $messages;

  /**
   * Implements hook_civicrm_check()
   *
   * @param array $messages
   *
   * @return void
   * @throws \CRM_Core_Exception
   */
  public function checkRequirements(array &$messages): void {
    $this->messages = $messages;
    $this->checkExtensionWorldpay();
    $this->checkExtensionContributiontransactlegacy();
    $this->checkIfSeparateMembershipPaymentEnabled();
    $this->checkExtensionSweetalert();
    $this->checkMultidomainJobs();
    $this->checkPaymentprocessorWebhooks();
    $messages = $this->messages;
  }

  /**
   * @param string $extensionName
   * @param string $minVersion
   * @param string $actualVersion
   */
  private function requireExtensionMinVersion(string $extensionName, string $minVersion, string $actualVersion): void {
    $actualVersionModified = $actualVersion;
    if (substr($actualVersion, -4) === '-dev') {
      $actualVersionModified = substr($actualVersion, 0, -4);
      $devMessageAlreadyDefined = FALSE;
      foreach ($this->messages as $message) {
        if ($message->getName() === __FUNCTION__ . $extensionName . '_requirements_dev') {
          // Another extension already generated the "Development version" message for this extension
          $devMessageAlreadyDefined = TRUE;
        }
      }
      if (!$devMessageAlreadyDefined) {
        $message = new \CRM_Utils_Check_Message(
          __FUNCTION__ . $extensionName . '_requirements_dev',
          E::ts('You are using a development version of %1 extension.',
            [1 => $extensionName]),
          E::ts('%1: Development version', [1 => $extensionName]),
          \Psr\Log\LogLevel::WARNING,
          'fa-code'
        );
        $this->messages[] = $message;
      }
    }

    if (version_compare($actualVersionModified, $minVersion) === -1) {
      $message = new \CRM_Utils_Check_Message(
        __FUNCTION__ . $extensionName . E::SHORT_NAME . '_requirements',
        E::ts('The %1 extension requires the %2 extension version %3 or greater but your system has version %4.',
          [
            1 => ucfirst(E::SHORT_NAME),
            2 => $extensionName,
            3 => $minVersion,
            4 => $actualVersion
          ]),
        E::ts('%1: Missing Requirements', [1 => ucfirst(E::SHORT_NAME)]),
        \Psr\Log\LogLevel::ERROR,
        'fa-exclamation-triangle'
      );
      $message->addAction(
        E::ts('Upgrade now'),
        NULL,
        'href',
        ['path' => 'civicrm/admin/extensions', 'query' => ['action' => 'update', 'id' => $extensionName, 'key' => $extensionName]]
      );
      $this->messages[] = $message;
    }
  }


  /**
   * @throws \CRM_Core_Exception
   */
  private function checkExtensionWorldpay(): void {
    $extensions = civicrm_api3('Extension', 'get', [
      'full_name' => 'uk.co.nfpservice.onlineworldpay',
    ]);

    if (!empty($extensions['id']) && ($extensions['values'][$extensions['id']]['status'] === 'installed')) {
      $this->messages[] = new \CRM_Utils_Check_Message(
        __FUNCTION__ . 'mjwshared_incompatible',
        E::ts('You have the uk.co.nfpservice.onlineworldpay extension installed.
        There are multiple versions of this extension on various sites and the source code has not been released.
        It is known to be cause issues with other payment processors and should be disabled'),
        E::ts('Incompatible Extension: uk.co.nfpservice.onlineworldpay'),
        \Psr\Log\LogLevel::WARNING,
        'fa-money'
      );
    }
  }

  /**
   * @throws \CRM_Core_Exception
   */
  private function checkExtensionContributiontransactlegacy(): void {
    $extensionName = 'contributiontransactlegacy';
    // Only on Drupal 7 (webform_civicrm 7.x-5.x) - do we have webform_civicrm installed?
    if (function_exists('module_exists') && \CRM_Core_Config::singleton()->userFramework === 'Drupal') {
      $extensions = civicrm_api3('Extension', 'get', [
        'full_name' => $extensionName,
      ]);

      if (module_exists('webform_civicrm') && (empty($extensions['id']) || ($extensions['values'][$extensions['id']]['status'] !== 'installed'))) {
        $message = new \CRM_Utils_Check_Message(
          __FUNCTION__ . 'mjwshared_recommended',
          E::ts('If you are using Drupal webform_civicrm to accept payments you should download and install the
            <strong><a href="https://civicrm.org/extensions/contribution-transact-api">contributiontransactlegacy</a></strong> extension.
            This fixes a number of issues that cause payments to fail with extensions such as <strong><a href="https://civicrm.org/extensions/stripe-payment-processor">Stripe</a></strong>.'),
          E::ts('Recommended Extension: contributiontransactlegacy'),
          \Psr\Log\LogLevel::WARNING,
          'fa-lightbulb-o'
        );
        $message->addAction(
          E::ts('Install now'),
          NULL,
          'href',
          ['path' => 'civicrm/admin/extensions', 'query' => ['action' => 'update', 'id' => $extensionName, 'key' => $extensionName]]
        );
        $this->messages[] = $message;
      }
    }
  }

  /**
   * We don't support "Separate Membership Payment" configuration
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function checkIfSeparateMembershipPaymentEnabled(): void {
    $separateMembershipPaymentNotSupportedProcessors = ['Stripe', 'Globalpayments'];
    $membershipBlocks = civicrm_api3('MembershipBlock', 'get', [
      'is_separate_payment' => 1,
      'is_active' => 1,
    ]);
    if ($membershipBlocks['count'] > 0) {
      $contributionPagesToCheck = [];
      foreach ($membershipBlocks['values'] as $blockDetails) {
        if ($blockDetails['entity_table'] !== 'civicrm_contribution_page') {
          continue;
        }
        $contributionPagesToCheck[] = $blockDetails['entity_id'];
      }
      $paymentProcessorIDs = PaymentProcessor::get(FALSE)
        ->addJoin('PaymentProcessorType AS payment_processor_type', 'INNER', ['payment_processor_type_id', '=', 'payment_processor_type.id'])
        ->addWhere('payment_processor_type.name', 'IN', $separateMembershipPaymentNotSupportedProcessors)
        ->execute()
        ->column('id');

      if (!empty($contributionPagesToCheck)) {
        $contributionPages = civicrm_api3('ContributionPage', 'get', [
          'return' => ['payment_processor'],
          'id' => ['IN' => $contributionPagesToCheck],
          'is_active' => 1,
        ]);
        foreach ($contributionPages['values'] as $contributionPage) {
          $enabledPaymentProcessors = is_array($contributionPage['payment_processor'])
            ? $contributionPage['payment_processor'] : explode(\CRM_Core_DAO::VALUE_SEPARATOR, $contributionPage['payment_processor']);
          foreach ($enabledPaymentProcessors as $enabledID) {
            if (in_array($enabledID, $paymentProcessorIDs)) {
              $message = new \CRM_Utils_Check_Message(
                __FUNCTION__ . 'mjwshared_requirements',
                E::ts('You need to disable "Separate Membership Payment" or disable the payment processors: %2 on contribution page %1 because it is not supported and will not work.
                See <a href="https://lab.civicrm.org/extensions/stripe/-/issues/134">Stripe#134</a>.',
                  [
                    1 => $contributionPage['id'],
                    2 => implode(', ', $separateMembershipPaymentNotSupportedProcessors),
                  ]),
                E::ts('Payments: Invalid configuration'),
                \Psr\Log\LogLevel::ERROR,
                'fa-money'
              );
              $this->messages[] = $message;
              return;
            }
          }
        }
      }
    }
  }

  /**
   * @throws \CRM_Core_Exception
   */
  private function checkExtensionSweetalert(): void {
    // sweetalert: recommended. If installed requires min version
    $extensionName = 'sweetalert';
    $extensions = civicrm_api3('Extension', 'get', [
      'full_name' => $extensionName,
    ]);

    if (empty($extensions['count']) || ($extensions['values'][$extensions['id']]['status'] !== 'installed')) {
      $message = new \CRM_Utils_Check_Message(
        __FUNCTION__ . 'mjwshared_recommended',
        E::ts('It is recommended that you install the <strong><a href="https://civicrm.org/extensions/sweetalert">sweetalert</a></strong> extension.
        This allows extensions such as Stripe to show useful messages to the user when processing payment.
        If this is not installed it will fallback to the browser "alert" message but you will
        not see some messages (such as <em>we are pre-authorizing your card</em> and <em>please wait</em>) and the feedback to the user will not be as helpful.'),
        E::ts('Recommended Extension: sweetalert'),
        \Psr\Log\LogLevel::NOTICE,
        'fa-lightbulb-o'
      );
      $message->addAction(
        E::ts('Install now'),
        NULL,
        'href',
        ['path' => 'civicrm/admin/extensions', 'query' => ['action' => 'update', 'id' => $extensionName, 'key' => $extensionName]]
      );
      $this->messages[] = $message;
      return;
    }
    if (isset($extensions['id']) && $extensions['values'][$extensions['id']]['status'] === 'installed') {
      $this->requireExtensionMinVersion($extensionName, self::MIN_VERSION_SWEETALERT, $extensions['values'][$extensions['id']]['version']);
    }
  }

  /**
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function checkMultidomainJobs(): void {
    $domains = Domain::get(FALSE)
      ->execute();
    if ($domains->count() <= 1) {
      return;
    }

    $jobs = civicrm_api3('Job', 'get', [
      'api_action' => "process_paymentprocessor_webhooks",
      'api_entity' => "job",
    ])['values'];

    $domainMissingJob = [];
    foreach ($domains as $domain) {
      foreach ($jobs as $job) {
        if ((int) $job['domain_id'] === $domain['id']) {
          // We found a job for this domain.
          continue 2;
        }
      }
      $domainMissingJob[$domain['id']] = "{$domain['id']}: {$domain['name']}";
    }

    if (!empty($domainMissingJob)) {
      $domainMessage = '<ul><li>' . implode('</li><li>', $domainMissingJob) . '</li></ul>';
      $message = new \CRM_Utils_Check_Message(
        __FUNCTION__ . 'mjwshared_multidomain',
        E::ts('You have multiple domains configured and some domains are missing the scheduled job "Job.process_paymentprocessor_webhooks": %1',
          [1 => $domainMessage]
        ),
        E::ts('Payments: Multidomain scheduled jobs'),
        \Psr\Log\LogLevel::WARNING,
        'fa-code'
      );
      $this->messages[] = $message;
    }
  }

  /**
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function checkPaymentprocessorWebhooks(): void {
    $paymentprocessorWebhooksProcessingCount = PaymentprocessorWebhook::get(FALSE)
      ->addSelect('row_count')
      ->addWhere('status', '=', 'processing')
      ->addWhere('created_date', '<', 'now-1hour')
      ->addOrderBy('created_date', 'DESC')
      ->execute()
      ->countMatched();

    if ($paymentprocessorWebhooksProcessingCount > 0) {
      $paymentprocessorWebhookProcessors = PaymentprocessorWebhook::get(FALSE)
        ->addSelect('payment_processor_id:label')
        ->addWhere('status', '=', 'processing')
        ->addWhere('created_date', '<', 'now-1hour')
        ->addGroupBy('payment_processor_id')
        ->execute();
      foreach ($paymentprocessorWebhookProcessors as $paymentprocessor) {
        if (!empty($paymentprocessor['payment_processor_id:label'])) {
          $paymentProcessorLabels[$paymentprocessor['payment_processor_id:label']] = $paymentprocessor['payment_processor_id:label'];
        }
      }
      $message = new \CRM_Utils_Check_Message(
        __FUNCTION__ . 'mjwshared_paymentprocessorwebhooks',
        E::ts('You have %1 payment processor webhooks in "processing" status for %2 payment processors.
        This means that the system started processing them but something went wrong that can\'t be fixed automatically.
        Please check and identify the problem. Then you can mark them for retry.',
          [1 => $paymentprocessorWebhooksProcessingCount, 2 => implode(',', $paymentProcessorLabels ?? [])]
        ),
        E::ts('Payment Processor Webhooks: Processing failed'),
        \Psr\Log\LogLevel::ERROR,
        'fa-code'
      );
      $message->addAction('Check now', FALSE, 'href', ['path' => 'civicrm/paymentprocessorwebhooks']);
      $this->messages[] = $message;
    }
  }

}
