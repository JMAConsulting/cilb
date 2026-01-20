<?php

namespace Drupal\nagios\EventSubscriber;

use Drupal\nagios\Controller\StatuspageController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Allow access to status page on maintenance mode.
 */
class MaintenanceModeSubscriber implements EventSubscriberInterface {

  /**
   * Make the status page available when Drupal is in maintenance mode.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onKernelRequestMaintenance(RequestEvent $event) {
    $config = \Drupal::config('nagios.settings');
    $request = $event->getRequest();
    $nagios_path = '/' . $config->get('nagios.statuspage.path');
    if ($request->getPathInfo() === $nagios_path) {
      $oController = new StatuspageController();
      if ($oController->access()->isAllowed()) {
        $event->setResponse($oController->content());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onKernelRequestMaintenance', 35];
    return $events;
  }

}
