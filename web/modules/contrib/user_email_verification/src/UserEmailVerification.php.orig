<?php

namespace Drupal\user_email_verification;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\user\UserInterface;
use Drupal\user_email_verification\Event\UserEmailVerificationEvents;
use Drupal\user_email_verification\Event\UserEmailVerificationBlockAccountEvent;
use Drupal\user_email_verification\Event\UserEmailVerificationCreateVerificationEvent;
use Drupal\user_email_verification\Event\UserEmailVerificationDeleteAccountEvent;
use Drupal\user_email_verification\Event\UserEmailVerificationRulesExtendedReminderMailSent;
use Drupal\user_email_verification\Event\UserEmailVerificationRulesReminderMailSent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * User email verification helper service.
 */
class UserEmailVerification implements UserEmailVerificationInterface {

  use StringTranslationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current primary database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The user_email_verification.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The user.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configUserSettings;

  /**
   * The system.site config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configSystemSite;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * User carma update queue.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue;

  /**
   * Mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The factory for configuration objects.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new DietService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The current primary database.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Component\Datetime\TimeInterface $datetime_time
   *   The time service.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue factory object.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   Mail manager service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current active user.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    ConfigFactoryInterface $config_factory,
    TimeInterface $datetime_time,
    QueueFactory $queue,
    MailManagerInterface $mail_manager,
    Token $token,
    AccountProxyInterface $current_user,
    LanguageManagerInterface $language_manager,
    EventDispatcherInterface $event_dispatcher
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->config = $config_factory->get('user_email_verification.settings');
    $this->configUserSettings = $config_factory->get('user.settings');
    $this->configSystemSite = $config_factory->get('system.site');
    $this->time = $datetime_time;
    $this->queue = $queue;
    $this->mailManager = $mail_manager;
    $this->token = $token;
    $this->currentUser = $current_user;
    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getValidateInterval() {
    return (int) $this->config->get('validate_interval');
  }

  /**
   * {@inheritdoc}
   */
  public function getNumReminders() {
    return (int) $this->config->get('num_reminders');
  }

  /**
   * {@inheritdoc}
   */
  public function getReminderInterval() {
    return (int) ceil($this->getValidateInterval() / ($this->getNumReminders() + 1));
  }

  /**
   * {@inheritdoc}
   */
  public function getSkipRoles() {
    return $this->config->get('skip_roles');
  }

  /**
   * {@inheritdoc}
   */
  public function getExtendedValidateInterval() {
    return (int) $this->config->get('extended_validate_interval');
  }

  /**
   * {@inheritdoc}
   */
  public function getMailSubject() {
    // Get configuration object directly from factory to have correct language.
    return (string) $this->configFactory->get('user_email_verification.settings')->get('mail_subject');
  }

  /**
   * {@inheritdoc}
   */
  public function getMailBody() {
    // Get configuration object directly from factory to have correct language.
    return (string) $this->configFactory->get('user_email_verification.settings')->get('mail_body');
  }

  /**
   * {@inheritdoc}
   */
  public function getExtendedMailSubject() {
    // Get configuration object directly from factory to have correct language.
    return (string) $this->configFactory->get('user_email_verification.settings')->get('extended_mail_subject');
  }

  /**
   * {@inheritdoc}
   */
  public function getExtendedMailBody() {
    // Get configuration object directly from factory to have correct language.
    return (string) $this->configFactory->get('user_email_verification.settings')->get('extended_mail_body');
  }

  /**
   * {@inheritdoc}
   */
  public function isExtendedPeriodEnabled() {
    return (bool) $this->config->get('extended_enable');
  }

  /**
   * {@inheritdoc}
   */
  public function isCreationAutoVerificationAllowed() {
    return !$this->config->get('no_creation_auto_verify');
  }

  /**
   * {@inheritdoc}
   */
  public function isUnblockAutoVerificationAllowed() {
    return !$this->config->get('no_unblock_auto_verify');
  }

  /**
   * {@inheritdoc}
   */
  public function shouldUserAccountDeleteOnEndOfExtendedInterval() {
    return (bool) $this->config->get('extended_end_delete_account');
  }

  /**
   * {@inheritdoc}
   */
  public function buildHmac($uid, $timestamp) {
    return Crypt::hmacBase64(
      $timestamp . $uid,
      Settings::getHashSalt() . $uid
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildVerificationUrl(UserInterface $user) {
    $timestamp = $this->time->getRequestTime();
    $hashed_pass = $this->buildHmac($user->id(), $timestamp);

    return Url::fromRoute(
      'user_email_verification.verify',
      [
        'uid' => $user->id(),
        'timestamp' => $timestamp,
        'hashed_pass' => $hashed_pass,
      ],
      [
        'absolute' => TRUE,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtendedVerificationUrl(UserInterface $user) {
    $timestamp = $this->time->getRequestTime();
    $hashed_pass = $this->buildHmac($user->id(), $timestamp);

    return Url::fromRoute(
      'user_email_verification.verify_extended',
      [
        'uid' => $user->id(),
        'timestamp' => $timestamp,
        'hashed_pass' => $hashed_pass,
      ],
      [
        'absolute' => TRUE,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function loadVerificationByUserId($uid) {

    return $this->database
      ->select(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME, 'uev')
      ->fields('uev')
      ->condition('uev.uid', intval($uid), '=')
      ->execute()
      ->fetchAssoc();
  }

  /**
   * {@inheritdoc}
   */
  public function setEmailVerifiedByUserId($uid) {

    return $this->database
      ->update(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME)
      ->condition('uid', $uid, '=')
      ->fields([
        'verified' => $this->time->getRequestTime(),
        'state' => UserEmailVerificationInterface::STATE_APPROVED,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function createVerification(UserInterface $user, $verify = FALSE) {

    $skip_roles = $this->getSkipRoles();
    $verified = 0;

    if ($skip_roles) {
      foreach ($skip_roles as $skip_role) {
        if ($user->hasRole($skip_role)) {
          $verified = $this->time->getRequestTime();
          break;
        }
      }
    }

    if (
      $this->currentUser->hasPermission('administer users') &&
      $this->isCreationAutoVerificationAllowed()
    ) {
      $verified = $this->time->getRequestTime();
    }

    // Provide an ability to other modules to modify
    // verified state (like auto-verify some specific users).
    $event = new UserEmailVerificationCreateVerificationEvent($user, (bool) $verified);
    $this->eventDispatcher->dispatch($event, UserEmailVerificationEvents::CREATE_VERIFICATION);

    if ($verify || $event->shouldBeVerified()) {
      $verified = $this->time->getRequestTime();
    }

    $this->database
      ->insert(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME)
      ->fields([
        'uid' => $user->id(),
        'verified' => $verified,
        'last_reminder' => $this->time->getRequestTime(),
        'reminders' => 0,
        'state' => $verified
        ? UserEmailVerificationInterface::STATE_APPROVED
        : UserEmailVerificationInterface::STATE_IN_PROGRESS,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteVerification(UserInterface $user) {
    $this->database
      ->delete(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME)
      ->condition('uid', $user->id())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function cronHandler() {
    $reminder_interval = $this->getReminderInterval();

    // Select those that need to be blocked.
    $uids = $this->getVerificationUidsFor('block_account', $reminder_interval);

    if ($uids) {
      $queue = $this->queue->get('user_email_verification_block_account');
      $uids = array_chunk($uids, UserEmailVerificationInterface::QUEUE_BLOCK_ACCOUNT_LIMIT);

      foreach ($uids as $uids_chunk) {
        $queue->createItem($uids_chunk);
      }
    }

    // Select those that need to be sent a reminder.
    $uids = $this->getVerificationUidsFor('reminders', $reminder_interval);

    if ($uids) {
      $queue = $this->queue->get('user_email_verification_reminders');
      $uids = array_chunk($uids, UserEmailVerificationInterface::QUEUE_REMINDERS_LIMIT);

      foreach ($uids as $uids_chunk) {
        $queue->createItem($uids_chunk);
      }
    }

    if ($this->isExtendedPeriodEnabled()) {
      // Delete accounts which have not verified their Email addresses within
      // extended time period. Similar to blocking users, but don't care about
      // reminder settings. Select those that need to be blocked.
      $uids = $this->getVerificationUidsFor('delete_account', $this->getExtendedValidateInterval());

      if ($uids) {
        $queue = $this->queue->get('user_email_verification_delete_account');
        $uids = array_chunk($uids, UserEmailVerificationInterface::QUEUE_DELETE_ACCOUNT_LIMIT);

        foreach ($uids as $uids_chunk) {
          $queue->createItem($uids_chunk);
        }
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function blockUserAccountById($uid) {
    $user = $this->entityTypeManager->getStorage('user')->load($uid);

    // If the account exists and is active, it should be blocked.
    if ($user instanceof UserInterface) {

      // Provide an ability to other modules to act before
      // account block (like prevent some accounts block).
      $event = new UserEmailVerificationBlockAccountEvent($user, $user->isActive());
      $this->eventDispatcher->dispatch($event, UserEmailVerificationEvents::BLOCK_ACCOUNT);

      if ($event->shouldBeBlocked()) {
        $user->block()->save();

        $this->setVerificationState($user->id(), UserEmailVerificationInterface::STATE_BLOCKED);

        if ($this->isExtendedPeriodEnabled()) {
          // If extended verification period is enabled - send Email to user
          // with a link which lets user to activate and verify the account
          // within defined time period.
          $this->mailManager->mail(
            'user_email_verification',
            'verify_extended',
            $user->getEmail(),
            $user->getPreferredLangcode(),
            ['user' => $user]
          );

          $rules_event = new UserEmailVerificationRulesExtendedReminderMailSent($user);
          $this->eventDispatcher->dispatch($rules_event, $rules_event::EVENT_NAME);
        }
      }
      else {
        // Some third party module modified the flow logic
        // (through the BLOCK_ACCOUNT event) - set "On hold"
        // state to prevent circular user account blocking.
        $this->setVerificationState($user->id(), UserEmailVerificationInterface::STATE_ON_HOLD);
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function sendVerifyMailById($uid) {
    $user = $this->entityTypeManager->getStorage('user')->load($uid);

    if ($user instanceof UserInterface) {

      $mail = $this->mailManager->mail(
        'user_email_verification',
        'verify',
        $user->getEmail(),
        $user->getPreferredLangcode(),
        ['user' => $user]
      );

      $rules_event = new UserEmailVerificationRulesReminderMailSent($user);
      $this->eventDispatcher->dispatch($rules_event, $rules_event::EVENT_NAME);

      return $mail && isset($mail['result']) ? $mail['result'] : FALSE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function sendVerifyBlockedMail(UserInterface $user) {

    $mail = $this->mailManager->mail(
      'user_email_verification',
      'verify_blocked',
      $this->configSystemSite->get('mail'),
      $this->languageManager->getDefaultLanguage()->getId(),
      ['user' => $user]
    );

    return $mail && isset($mail['result']) ? $mail['result'] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function remindUserById($uid) {

    if ($this->isReminderNeeded($uid)) {
      $this->sendVerifyMailById($uid);

      // Always increase the reminder mail counter by one even if sending
      // the mail failed. Some mail systems like Mandrill return FALSE if
      // they cannot deliver the mail to an invalid address. We need to
      // increase counter to make sure that users get blocked at some point.
      $this->database
        ->update(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME)
        ->condition('uid', $uid, '=')
        ->expression('reminders', 'reminders + :amount', [':amount' => 1])
        ->fields(['last_reminder' => $this->time->getRequestTime()])
        ->execute();
    }

  }

  /**
   * {@inheritdoc}
   */
  public function deleteUserAccountById($uid) {
    $user = $this->entityTypeManager->getStorage('user')->load($uid);

    if ($user instanceof UserInterface) {

      // Provide an ability to other modules to act before
      // account delete (like prevent some accounts delete).
      $event = new UserEmailVerificationDeleteAccountEvent($user, TRUE);
      $this->eventDispatcher->dispatch($event, UserEmailVerificationEvents::DELETE_ACCOUNT);

      if ($event->shouldBeDeleted()) {
        $this->setVerificationState($user->id(), UserEmailVerificationInterface::STATE_DELETED);

        // Delete the user account only if this action was chosen.
        if ($this->shouldUserAccountDeleteOnEndOfExtendedInterval()) {

          // Notify account about cancellation.
          _user_mail_notify('status_canceled', $user);

          // Init user cancel process.
          user_cancel([], $user->id(), $this->configUserSettings->get('cancel_method'));

          // user_cancel() initiates a batch process. Run it manually.
          $batch =& batch_get();
          $batch['progressive'] = FALSE;
          if (PHP_SAPI === 'cli' && function_exists('drush_backend_batch_process')) {
            drush_backend_batch_process();
          }
          else {
            batch_process();
          }
        }
      }
      else {
        // Some third party module modified the flow logic
        // (through the DELETE_ACCOUNT event) - set "On hold"
        // state to prevent circular user account deletion.
        $this->setVerificationState($user->id(), UserEmailVerificationInterface::STATE_ON_HOLD);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isReminderNeeded($uid) {

    // Only send the reminder if the user is not verified yet
    // and the number of reminders has not been reached yet.
    return (bool) $this->database
      ->select(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME, 'uev')
      ->fields('uev', ['uid'])
      ->condition('uev.uid', $uid, '=')
      ->condition('uev.verified', 0, '=')
      ->condition('uev.reminders', $this->getNumReminders(), '<')
      ->condition('uev.last_reminder', $this->time->getRequestTime() - $this->getReminderInterval(), '<')
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function isVerificationNeeded($uid = 0) {

    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $skip_roles = $this->getSkipRoles();

    $query = $this->database
      ->select(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME, 'uev')
      ->fields('uev', ['uid'])
      ->condition('uev.verified', 0, '=')
      ->condition('uev.uid', $uid, '=');

    if ($skip_roles) {
      $query->leftJoin('user__roles', 'ur', 'ur.entity_id = uev.uid');

      $or = $query->orConditionGroup()
        ->condition('ur.roles_target_id', $skip_roles, 'NOT IN')
        // Normal registered users don't have entry in the users_roles table.
        ->isNull('ur.roles_target_id');

      $query->condition($or);
      $query->distinct();
    }

    return (bool) $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function initEmailMessage($key, array &$message, array $params) {

    /** @var \Drupal\user\UserInterface $user */
    $user = $params['user'];

    // Care about correct configuration translation usage.
    $language = $this->languageManager->getLanguage($user->getPreferredLangcode());
    $original_language = $this->languageManager->getConfigOverrideLanguage();
    $this->languageManager->setConfigOverrideLanguage($language);

    $token_data = ['user' => $user];
    $token_options = ['langcode' => $user->getPreferredLangcode(), 'clear' => TRUE];

    switch ($key) {

      case 'verify':
        $message['subject'] = $this->token->replace((string) $this->getMailSubject(), $token_data, $token_options);
        $message['body'][] = $this->token->replace((string) $this->getMailBody(), $token_data, $token_options);
        break;

      case 'verify_blocked':
        $message['subject'] = $this->t('A blocked account verified Email.');
        $message['body'][] = $this->t(
          'Blocked account with name: @name, ID: @id verified own Email: @email',
          [
            '@id' => $user->id(),
            '@name' => $user->getAccountName(),
            '@email' => $user->getEmail(),
          ]
        );
        $message['body'][] = Url::fromRoute('entity.user.edit_form', ['user' => $user->id()], ['absolute' => TRUE])->toString();
        $message['body'][] = $this->t('If the account is not blocked for other reason, please unblock the account.');
        break;

      case 'verify_extended':
        $message['subject'] = $this->token->replace((string) $this->getExtendedMailSubject(), $token_data, $token_options);
        $message['body'][] = $this->token->replace((string) $this->getExtendedMailBody(), $token_data, $token_options);
        break;
    }

    $this->languageManager->setConfigOverrideLanguage($original_language);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserByNameOrEmail($name_or_email, $active_only = TRUE) {

    if (!$name_or_email) {
      return NULL;
    }

    $user_storage = $this->entityTypeManager->getStorage('user');
    $query = $user_storage->getQuery();
    $name_email_condition = $query->orConditionGroup()
      ->condition('name', $name_or_email)
      ->condition('mail', $name_or_email);
    $query->condition($name_email_condition);

    if ($active_only) {
      $query->condition('status', 1);
    }
    $query->accessCheck(FALSE);
    $uids = $query->execute();

    $uid = reset($uids);

    return $uid ? $user_storage->load($uid) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isVerificationPeriodExceeded($uid) {

    return (bool) $this->database
      ->select(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME, 'uev')
      ->fields('uev', ['uid'])
      ->condition('uev.uid', $uid, '=')
      ->condition('uev.verified', 0, '=')
      ->condition('uev.state', UserEmailVerificationInterface::STATE_IN_PROGRESS, '=')
      ->condition('uev.reminders', $this->getNumReminders(), '>=')
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function isVerificationExtendedPeriodExceeded($uid) {

    return (bool) $this->database
      ->select(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME, 'uev')
      ->fields('uev', ['uid'])
      ->condition('uev.uid', $uid, '=')
      ->condition('uev.verified', 0, '=')
      ->condition('uev.state', UserEmailVerificationInterface::STATE_IN_PROGRESS, '=')
      ->condition('uev.reminders', $this->getNumReminders(), '>=')
      ->condition('uev.last_reminder', $this->time->getRequestTime() - $this->getExtendedValidateInterval(), '<')
      ->execute()
      ->fetchField();
  }

  /**
   * Return list of user IDs related to requested reason and interval pair.
   *
   * @param string $reason
   *   The reason name.
   * @param int $interval
   *   Rhe time interval in seconds.
   *
   * @return array
   *   List of user IDs related to requested reason and interval pair.
   */
  protected function getVerificationUidsFor($reason, $interval) {
    $num_reminders = $this->getNumReminders();
    $skip_roles = $this->getSkipRoles();

    $query = $this->database->select(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME, 'uev');

    if ($skip_roles) {
      $query->leftJoin('user__roles', 'ur', 'ur.entity_id = uev.uid');

      $or = $query->orConditionGroup()
        ->condition('ur.roles_target_id', $skip_roles, 'NOT IN')
        // Normal registered users don't have entry in the users_roles table.
        ->isNull('ur.roles_target_id');

      $query->condition($or);
      $query->distinct();
    }

    $query
      ->fields('uev', ['uid'])
      ->condition('uev.verified', 0, '=')
      ->condition('uev.uid', 1, '>')
      ->condition('uev.last_reminder', $this->time->getRequestTime() - $interval, '<');

    switch ($reason) {

      case 'block_account':
        $query->condition('uev.state', UserEmailVerificationInterface::STATE_IN_PROGRESS, '=');
        $query->condition('uev.reminders', $num_reminders, '>=');
        break;

      case 'reminders':
        $query->condition('uev.state', UserEmailVerificationInterface::STATE_IN_PROGRESS, '=');
        $query->condition('uev.reminders', $num_reminders, '<');
        break;

      case 'delete_account':
        // This condition prevents circular user delete attempts in case
        // "When cancelling a user account" was set to
        // "Disable the account and keep its content." or
        // "Disable the account and un-publish its content.".
        $query->condition('uev.state', UserEmailVerificationInterface::STATE_DELETED, '<>');
        break;
    }

    return $query
      ->execute()
      ->fetchAllKeyed(0, 0);
  }

  /**
   * Set user account verification state.
   *
   * @param int $uid
   *   User ID to change verification state for.
   * @param int $state
   *   State to set.
   */
  protected function setVerificationState($uid, $state) {
    $this->database
      ->update(UserEmailVerificationInterface::VERIFICATION_TABLE_NAME)
      ->condition('uid', $uid, '=')
      ->fields(['state' => $state])
      ->execute();
  }

}
