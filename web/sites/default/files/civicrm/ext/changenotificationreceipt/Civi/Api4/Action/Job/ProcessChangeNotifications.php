<?php

namespace Civi\Api4\Action\Job;

use Civi\Api4\Contact;
use Civi\Api4\Generic\Result;
use CRM_ChangeNotificationReceipt_Queue as Queue;

/**
 * Processes the change-notification queue.
 */
class ProcessChangeNotifications extends \Civi\Api4\Generic\AbstractAction {

  /**
   * Message-template title for the email body.
   *
   * @var string
   */
  protected $emailTemplateTitle = 'CILB Change Notification - Email';

  /**
   * Message-template title that holds the PDF receipt HTML.
   *
   * @var string
   */
  protected $receiptTemplateTitle = 'CILB Change Notification - Receipt';

  /**
   * When TRUE, render everything but do not send email or mark rows processed.
   *
   * @var bool
   */
  protected $dryRun = FALSE;

  /**
   * Human-friendly section heading per source table for the receipt.
   */
  private const SECTIONS = [
    'civicrm_contact' => 'Contact Information',
    'civicrm_email' => 'Contact Information',
    'civicrm_phone' => 'Contact Information',
    'civicrm_address' => 'Contact Information',
    'civicrm_participant' => 'Exam Registration',
    'civicrm_contribution' => 'Payments',
  ];

  public function _run(Result $result) {
    $emailTemplate = $this->getTemplate($this->emailTemplateTitle);
    $receiptTemplate = $this->getTemplate($this->receiptTemplateTitle);
    if (empty($emailTemplate['id']) || empty($receiptTemplate['msg_html'])) {
      throw new \CRM_Core_Exception('Change-notification message templates are missing. Re-enable the extension to (re)create them.');
    }
    $emailTemplateId = (int) $emailTemplate['id'];
    $receiptHtml = (string) $receiptTemplate['msg_html'];

    [$fromName, $fromEmail] = \CRM_Core_BAO_Domain::getNameAndEmail();
    $from = "\"{$fromName}\" <{$fromEmail}>";

    $contactIds = Queue::getPendingContactIds();
    $summary = ['contacts' => count($contactIds), 'sent' => 0, 'skipped' => 0, 'errors' => 0];

    foreach ($contactIds as $contactId) {
      $rows = Queue::getPendingForContact($contactId);
      if (!$rows) {
        continue;
      }
      $rowIds = array_column($rows, 'id');

      $contact = Contact::get(FALSE)
        ->addSelect('display_name', 'is_deleted', 'do_not_email', 'is_opt_out', 'email_primary.email')
        ->addWhere('id', '=', $contactId)
        ->execute()
        ->first();

      $email = $contact['email_primary.email'] ?? NULL;
      if (empty($contact) || !empty($contact['is_deleted']) || !empty($contact['do_not_email'])
        || !empty($contact['is_opt_out']) || empty($email)) {
        if (!$this->dryRun) {
          Queue::markProcessed($rowIds, Queue::STATUS_SKIPPED);
        }
        $summary['skipped']++;
        continue;
      }

      $sections = $this->buildSections($rows);
      $tplParams = [
        'contactDisplayName' => $contact['display_name'],
        'generatedDate' => \CRM_Utils_Date::customFormat(date('YmdHis')),
        'sections' => $sections,
      ];
      $html = \CRM_Core_Smarty::singleton()->fetchWith('string:' . $receiptHtml, $tplParams);

      if ($this->dryRun) {
        $result[] = [
          'contact_id' => $contactId,
          'email' => $email,
          'queue_rows' => count($rows),
          'receipt_html_bytes' => strlen($html),
          'receipt_html' => $html,
        ];
        continue;
      }

      try {
        $attachment = \CRM_Utils_Mail::appendPDF('CILB-Updated-Receipt.pdf', $html);
        [$sent] = \CRM_Core_BAO_MessageTemplate::sendTemplate([
          'messageTemplateID' => $emailTemplateId,
          'contactId' => $contactId,
          'from' => $from,
          'toName' => $contact['display_name'],
          'toEmail' => $email,
          'attachments' => [$attachment],
        ]);
        if ($sent) {
          Queue::markProcessed($rowIds, Queue::STATUS_SENT);
          $summary['sent']++;
        }
        else {
          Queue::markProcessed($rowIds, Queue::STATUS_ERROR);
          $summary['errors']++;
        }
      }
      catch (\Throwable $e) {
        \Civi::log()->error('changenotificationreceipt: failed to notify contact {cid}: {msg}', [
          'cid' => $contactId,
          'msg' => $e->getMessage(),
        ]);
        Queue::markProcessed($rowIds, Queue::STATUS_ERROR);
        $summary['errors']++;
      }
    }

    $result['summary'] = $summary;
    return $result;
  }

  private function buildSections(array $rows): array {
    $sections = [];
    foreach ($rows as $row) {
      $heading = self::SECTIONS[$row['entity_table']] ?? 'Other Changes';
      $sections[$heading][] = [
        'action' => ucfirst($row['action']),
        'changes' => $row['changes'],
      ];
    }
    $out = [];
    foreach ($sections as $heading => $entries) {
      $out[] = ['heading' => $heading, 'entries' => $entries];
    }
    return $out;
  }

  private function getTemplate(string $title): ?array {
    return \Civi\Api4\MessageTemplate::get(FALSE)
      ->addSelect('id', 'msg_html')
      ->addWhere('msg_title', '=', $title)
      ->addWhere('is_active', '=', TRUE)
      ->addOrderBy('id', 'ASC')
      ->setLimit(1)
      ->execute()
      ->first();
  }

}
