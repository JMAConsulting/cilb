<?php

use Civi\Api4\Mailing;
use Civi\Api4\MailingJob;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 */
class CRM_SesTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__ . '/..')
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    // The extension's own install (hook_civicrm_managed) may already have
    // created this mailing on a real site; remove it so each test starts
    // from a known "does not exist yet" state (rolled back after the test).
    $existing = Mailing::get(FALSE)
      ->addWhere('name', '=', 'Ses Transactional Emails')
      ->execute();
    foreach ($existing as $mailing) {
      Mailing::delete(FALSE)->addWhere('id', '=', $mailing['id'])->execute();
    }
  }

  public function testCreateTransactionalMailingCreatesOnFirstCall(): void {
    $result = CRM_Ses::createTransactionalMailing();
    $this->assertTrue($result);

    $mailing = Mailing::get(FALSE)
      ->addWhere('name', '=', 'Ses Transactional Emails')
      ->execute()->single();
    $this->assertSame('Ses Transactional Emails (do not delete)', $mailing['subject']);
    $this->assertTrue((bool) $mailing['url_tracking']);
    $this->assertFalse((bool) $mailing['forward_replies']);
    $this->assertFalse((bool) $mailing['auto_responder']);
    $this->assertTrue((bool) $mailing['open_tracking']);
    $this->assertFalse((bool) $mailing['is_completed']);

    $job = MailingJob::get(FALSE)
      ->addWhere('mailing_id', '=', $mailing['id'])
      ->execute()->single();
    $this->assertSame('Complete', $job['status']);
    $this->assertSame('Special: All Ses transactional emails', $job['job_type']);
  }

  public function testCreateTransactionalMailingIsIdempotent(): void {
    $this->assertTrue(CRM_Ses::createTransactionalMailing());
    $this->assertFalse(CRM_Ses::createTransactionalMailing(), 'A second call must not create a duplicate.');

    $count = Mailing::get(FALSE)
      ->addWhere('name', '=', 'Ses Transactional Emails')
      ->selectRowCount()
      ->execute()->count();
    $this->assertSame(1, $count);
  }

}
