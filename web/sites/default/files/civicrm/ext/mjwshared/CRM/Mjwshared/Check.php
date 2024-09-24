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

use CRM_Mjwshared_ExtensionUtil as E;

/**
 * Class CRM_Mjwshared_Check
 */
class CRM_Mjwshared_Check {

  const MIN_VERSION_SWEETALERT = '1.5';

  /**
   * @var array
   */
  private array $messages;

  /**
   * CRM_Mjwshared_Check constructor.
   *
   * @param array $messages
   *
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function __construct($messages) {
    $this->messages = $messages;
  }

  /**
   * @return array
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function checkRequirements() {
    $this->checkExtensionWorldpay();
    // $this->checkExtensionMinifier();
    $this->checkExtensionContributiontransactlegacy();
    $this->checkIfSeparateMembershipPaymentEnabled();
    $this->checkExtensionSweetalert();
    $this->checkMultidomainJobs();
    return $this->messages;
  }

  /**
   * @param string $extensionName
   * @param string $minVersion
   * @param string $actualVersion
   */
  private function requireExtensionMinVersion(string $extensionName, string $minVersion, string $actualVersion) {
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
  private function checkExtensionWorldpay() {
    $extensions = civicrm_api3('Extension', 'get', [
      'full_name' => 'uk.co.nfpservice.onlineworldpay',
    ]);

    if (!empty($extensions['id']) && ($extensions['values'][$extensions['id']]['status'] === 'installed')) {
      $this->messages[] = new CRM_Utils_Check_Message(
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
  private function checkExtensionMinifier() {
    $extensionName = 'minifier';
    $extensions = civicrm_api3('Extension', 'get', [
      'full_name' => $extensionName,
    ]);

    if (empty($extensions['count']) || ($extensions['values'][$extensions['id']]['status'] !== 'installed')) {
      $message = new CRM_Utils_Check_Message(
        __FUNCTION__ . 'mjwshared_recommended',
        E::ts('It is recommended that you download and install the <strong><a href="https://civicrm.org/extensions/minifier">minifier</a></strong> extension.
               This will improve the page-load speeds for JS/CSS assets included with extensions such as <strong><a href="https://civicrm.org/extensions/stripe-payment-processor">Stripe</a></strong>.'),
        E::ts('Recommended Extension: minifier'),
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
    }
  }

  /**
   * @throws \CRM_Core_Exception
   */
  private function checkExtensionContributiontransactlegacy() {
    $extensionName = 'contributiontransactlegacy';
    // Only on Drupal 7 (webform_civicrm 7.x-5.x) - do we have webform_civicrm installed?
    if (function_exists('module_exists') && CRM_Core_Config::singleton()->userFramework === 'Drupal') {
      $extensions = civicrm_api3('Extension', 'get', [
        'full_name' => $extensionName,
      ]);

      if (module_exists('webform_civicrm') && (empty($extensions['id']) || ($extensions['values'][$extensions['id']]['status'] !== 'installed'))) {
        $message = new CRM_Utils_Check_Message(
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
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function checkIfSeparateMembershipPaymentEnabled() {
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
      $paymentProcessorIDs = \Civi\Api4\PaymentProcessor::get(FALSE)
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
              ? $contributionPage['payment_processor'] : explode(CRM_Core_DAO::VALUE_SEPARATOR, $contributionPage['payment_processor']);
          foreach ($enabledPaymentProcessors as $enabledID) {
            if (in_array($enabledID, $paymentProcessorIDs)) {
              $message = new CRM_Utils_Check_Message(
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
  private function checkExtensionSweetalert() {
    // sweetalert: recommended. If installed requires min version
    $extensionName = 'sweetalert';
    $extensions = civicrm_api3('Extension', 'get', [
      'full_name' => $extensionName,
    ]);

    if (empty($extensions['count']) || ($extensions['values'][$extensions['id']]['status'] !== 'installed')) {
      $message = new CRM_Utils_Check_Message(
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
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function checkMultidomainJobs() {
    $domains = \Civi\Api4\Domain::get(FALSE)
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
      $message = new CRM_Utils_Check_Message(
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

}
