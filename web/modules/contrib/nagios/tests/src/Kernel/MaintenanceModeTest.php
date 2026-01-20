<?php

namespace Drupal\Tests\nagios\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\nagios\Controller\StatuspageController;
use Drupal\nagios\EventSubscriber\MaintenanceModeSubscriber;
use Drupal\system\Form\SiteMaintenanceModeForm;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Tests the functionality to monitor cron.
 *
 * @group nagios
 */
class MaintenanceModeTest extends EntityKernelTestBase {

  use ProphecyTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['nagios'];

  /**
   * Perform any initial set up tasks that run before every test method.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('nagios');
    StatuspageController::setNagiosStatusConstants();
  }

  /**
   * Check if status page is accessible during maintenance.
   */
  public function testSubscriber() {
    $subscriber = new MaintenanceModeSubscriber();
    $get_response_event = $this->prophesize(RequestEvent::class);
    $request = $this->prophesize(Request::class);

    /** @noinspection PhpUndefinedMethodInspection */
    $request->getPathInfo()->willReturn('/nagios');

    /** @noinspection PhpUndefinedMethodInspection */
    $get_response_event->getRequest()
      ->willReturn($request->reveal())
      ->shouldBeCalled();

    $content = '';
    $set_response_content = function ($args) use (&$content) {
      /** @var \Symfony\Component\HttpFoundation\Response $response */
      $response = $args[0];
      $content = $response->getContent();
    };

    /** @noinspection PhpUndefinedMethodInspection */
    $get_response_event->setResponse(Argument::any())
      ->will($set_response_content)
      ->shouldBeCalled();

    $config = \Drupal::configFactory()->getEditable('nagios.settings');
    foreach (['cron', 'maintenance', 'watchdog'] as $check) {
      $config->set('nagios.function.' . $check, FALSE);
    }
    $config->set('nagios.statuspage.enabled', TRUE);
    $config->save();

    $_SERVER['HTTP_USER_AGENT'] = $config->get('nagios.ua');

    /** @noinspection PhpParamsInspection */
    $subscriber->onKernelRequestMaintenance($get_response_event->reveal());

    self::assertSame("\nnagios=OK,  | \n", $content);
  }

  /**
   * Check if nagios reports maintenance mode.
   */
  public function testMaintenanceModeCheck() {
    self::assertSame(NAGIOS_STATUS_OK, nagios_check_maintenance()['data']['status']);
    $form_object = SiteMaintenanceModeForm::create(\Drupal::getContainer());
    $form = [];
    $form_state = new FormState();
    $form_object->submitForm($form, $form_state->setValue('maintenance_mode', 1));
    self::assertSame(NAGIOS_STATUS_CRITICAL, nagios_check_maintenance()['data']['status']);
  }

}
