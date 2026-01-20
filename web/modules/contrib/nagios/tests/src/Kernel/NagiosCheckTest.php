<?php

namespace Drupal\Tests\nagios\Kernel;

use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\nagios\Controller\StatuspageController;

/**
 * Tests the functionality to monitor cron.
 *
 * @group nagios
 */
class NagiosCheckTest extends EntityKernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['nagios', 'user'];

  /**
   * Perform any initial set up tasks that run before every test method.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('nagios');
    StatuspageController::setNagiosStatusConstants();
  }

  /**
   * Test if nagios_check_elysia_cron works.
   */
  public function testElysiaCronCheck() {
    $conn = Database::getConnection();
    $conn->query('CREATE TABLE {elysia_cron} (last_aborted bigint, name varchar(8), last_abort_function varchar(8))');
    $status = nagios_check_elysia_cron()['data']['status'];
    self::assertEquals(NAGIOS_STATUS_OK, $status);

    $conn->query("INSERT INTO {elysia_cron} VALUES (1, 'toad', 'toad')");
    $status = nagios_check_elysia_cron()['data']['status'];
    self::assertEquals(NAGIOS_STATUS_CRITICAL, $status);

    $conn->query('DROP TABLE {elysia_cron}');
  }

  /**
   * Test if last cron run is reported.
   */
  public function testCronCheck() {
    // Set last run to an old date.
    \Drupal::state()->set('system.cron_last', 0);

    // Run check function, expect warning.
    $result1 = nagios_check_cron();
    self::assertSame(2, $result1['data']['status'], "Check critical response");

    // Run cron.
    /** @var \Drupal\Core\CronInterface $cron */
    $cron = \Drupal::service('cron');
    $cron->run();

    // Run check function, expect no warning.
    $result2 = nagios_check_cron();
    self::assertSame(0, $result2['data']['status'], "Check ok response");
  }

  /**
   * Test if status page works.
   */
  public function testStatuspage() {
    $statuspage_controller = new StatuspageController();
    $_SERVER['HTTP_USER_AGENT'] = 'Test';
    self::assertStringContainsString(
      "nagios=UNKNOWN, DRUPAL:UNKNOWN=Unauthorized |",
      $statuspage_controller->content()->getContent());

    $config = \Drupal::configFactory()->getEditable('nagios.settings');
    foreach (['cron', 'maintenance', 'watchdog'] as $check) {
      $config->set('nagios.function.' . $check, FALSE);
    }
    $config->save();
    $_SERVER['HTTP_USER_AGENT'] = 'Nagios';
    self::assertStringContainsString(
      "nagios=OK,",
      $statuspage_controller->content()->getContent());

    $config->set('nagios.statuspage.getparam', TRUE);
    $config->save();
    $_SERVER['HTTP_USER_AGENT'] = 'Test';
    self::assertStringContainsString(
      "nagios=UNKNOWN, DRUPAL:UNKNOWN=Unauthorized |",
      $statuspage_controller->content()->getContent());

    $_GET['unique_id'] = 'Nagios';
    self::assertStringContainsString(
      "nagios=OK,",
      $statuspage_controller->content()->getContent());

    self::assertInstanceOf(AccessResultNeutral::class, $statuspage_controller->access());
    self::assertFalse($statuspage_controller->access()->isAllowed());

    $config->set('nagios.statuspage.enabled', TRUE);
    $config->save();
    self::assertTrue($statuspage_controller->access()->isAllowed());
  }

  /**
   * Test what happens if watchdog module is not active, but nagios attempts to check it.
   */
  public function testWatchdogIfNotEnabled() {
    $expected = [
      'status' => 3,
      'type' => 'state',
      'text' => 'Unable to SELECT FROM {watchdog}',
    ];
    self::assertEquals($expected, nagios_check_watchdog()['data']);
  }

}
