<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentMethodType;

use Drupal\entity\BundleFieldDefinition;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;

/**
 * Provides the fondy payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "fondy",
 *   label = @Translation("Fondy"),
 * )
 */
class Fondy extends PaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method)
  {
      return 'fondy';
  }
}
