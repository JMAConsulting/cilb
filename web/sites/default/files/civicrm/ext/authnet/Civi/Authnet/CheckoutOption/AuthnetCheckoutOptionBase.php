<?php

namespace Civi\Authnet\CheckoutOption;

use Civi\Afform\Event\AfformValidateEvent;
use Civi\Checkout\AfformCheckoutOptionInterface;
use Civi\Checkout\CheckoutOptionInterface;

abstract class AuthnetCheckoutOptionBase implements CheckoutOptionInterface, AfformCheckoutOptionInterface {

  /**
   * @var array
   * The PaymentProcessor record
   */
  protected array $liveConnection;

  /**
   * @var array
   * The test PaymentProcessor record
   */
  protected array $testConnection;

  public function __construct(array $liveConnection, array $testConnection) {
    $this->liveConnection = $liveConnection;
    $this->testConnection = $testConnection;
  }

  abstract public function getLabel(): string;

  protected function getConnectionDetails(bool $testMode = FALSE): array {
    return $testMode ? $this->testConnection : $this->liveConnection;
  }

  public function validate(AfformValidateEvent $event): void {
    // no specific validation rules
  }

  abstract public function getAfformSettings(bool $testMode): array;

  public function getAfformModule(): ?string {
    return NULL;
  }

  public function getPaymentProcessorId(bool $testMode = FALSE): ?int {
    return $testMode ? $this->testConnection['id'] : $this->liveConnection['id'];
  }

  protected function getQuickformProcessor(bool $testMode = FALSE): \CRM_Core_Payment_AuthorizeNetCommon {
    $id = $this->getConnectionDetails($testMode)['id'];
    return \Civi\Payment\System::singleton()->getById($id);
  }

}
