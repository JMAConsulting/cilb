<?php

namespace Civi\Authnet;

use Civi\Checkout\CheckoutOptionUtils;
use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @service civi.authnet.checkoutOptions
 */
class CheckoutOptions extends AutoService implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      'civi.checkout.options' => 'addCheckoutOptions',
    ];
  }

  public function addCheckoutOptions(GenericHookEvent $e): void {
    $authnetPairs = CheckoutOptionUtils::getPaymentProcessorPairs(['AuthNetAcceptjs', 'AuthorizeNetCreditcard']);

    foreach ($authnetPairs as $name => $pair) {
      $e->options["acceptjs_{$name}"] = new CheckoutOption\Acceptjs($pair['live'], $pair['test']);
      $e->options["authnet_{$name}"] = new CheckoutOption\AuthnetCreditcard($pair['live'], $pair['test']);
      // @fixme: Add bank account (AuthnetEcheck)
    }
  }

}
