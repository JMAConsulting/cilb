<?php

namespace Drupal\Tests\nagios\Kernel;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\nagios\Controller\StatuspageController;

/**
 * Tests the functionality to report dblog/watchdog entries.
 *
 * @group nagios
 */
class WatchdogCheckTest extends EntityKernelTestBase {

  use LoggerChannelTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['nagios', 'user', 'dblog'];

  /**
   * Perform any initial set up tasks that run before every test method.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('nagios');
    $this->installSchema('dblog', 'watchdog');
    StatuspageController::setNagiosStatusConstants();
  }

  /**
   * No records in dblog table.
   */
  public function testEmptyWatchdog() {
    $this->assertAllGreen();
  }

  /**
   * Helper.
   */
  private function assertAllGreen() {
    $expected = [
      'status' => 0,
      'type' => 'state',
      'text' => '',
    ];
    self::expectWatchdog($expected);
  }

  /**
   * Helper.
   */
  private static function expectWatchdog($expected) {
    $actual = nagios_check_watchdog()['data'];
    $actual['text'] = preg_replace('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} /', '', $actual['text']);
    self::assertEquals($expected, $actual);
  }

  /**
   * Minimum level “warning” is the default.
   */
  public function testWithLevelWarning() {
    $this->getLogger('test')->info('info');
    $this->assertAllGreen();

    $this->getLogger('test')->warning('warning');
    $expected = [
      'status' => NAGIOS_STATUS_WARNING,
      'type' => 'state',
      'text' => 'wid=2 test warning',
    ];
    self::expectWatchdog($expected);

    $this->getLogger('test')->error('error');
    $expected = [
      'status' => NAGIOS_STATUS_CRITICAL,
      'type' => 'state',
      'text' => 'wid=3 test error, wid=2 test warning',
    ];
    self::expectWatchdog($expected);
  }

  /**
   * Negate false: ignore 'onion' channel.
   * Negate true: ignore 'garlic' channel.
   */
  public function testChannelFilter() {
    $config = \Drupal::configFactory()->getEditable('nagios.settings');
    $config->set('nagios.limit_watchdog.channel_filter', ['onion']);
    $config->set('nagios.limit_watchdog.negate', FALSE /* Ignore */);
    $config->save();

    $this->getLogger('onion')->error('error');
    $this->getLogger('garlic')->error('error');

    $expected = [
      'status' => NAGIOS_STATUS_CRITICAL,
      'type' => 'state',
      'text' => 'wid=2 garlic error',
    ];
    self::expectWatchdog($expected);

    $config->set('nagios.limit_watchdog.negate', TRUE /* Include */);
    $config->save();

    $expected = [
      'status' => NAGIOS_STATUS_CRITICAL,
      'type' => 'state',
      'text' => 'wid=1 onion error',
    ];
    self::expectWatchdog($expected);
  }

  /**
   * Show more recent problems first.
   */
  public function testOrder() {
    $this->getLogger('test')->warning('warning 1');
    sleep(2);
    $this->getLogger('test')->warning('warning 2');
    $expected = [
      'status' => NAGIOS_STATUS_WARNING,
      'type' => 'state',
      'text' => 'wid=2 test warning 2, wid=1 test warning 1',
    ];
    self::expectWatchdog($expected);
  }

  /**
   * Minimum level “error” should ignore warnings.
   */
  public function testWithLevelError() {
    $config = \Drupal::configFactory()->getEditable('nagios.settings');
    $config->set('nagios.min_report_severity', NAGIOS_STATUS_CRITICAL);
    $config->save();

    $this->getLogger('test')->info('info');
    $this->assertAllGreen();

    $this->getLogger('test')->warning('warning');
    $this->assertAllGreen();

    $this->getLogger('test')->error('error');
    $expected = [
      'status' => NAGIOS_STATUS_CRITICAL,
      'type' => 'state',
      'text' => 'wid=3 test error',
    ];
    self::expectWatchdog($expected);
  }

}
