<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsUpdatingStoredPaymentMethodsInterface;

/**
 * Provides the interface for the commerce_fondy payment gateway.
 *
 * The OnsitePaymentGatewayInterface is the base interface which all on-site
 * gateways implement. The other interfaces signal which additional capabilities
 * the gateway has. The gateway plugin is free to expose additional methods,
 * which would be defined below.
 */
interface FondyInterface extends OffsitePaymentGatewayInterface, SupportsAuthorizationsInterface, SupportsRefundsInterface
{
    /**
     * Get the Fondy API public key.
     *
     * @return string
     *   The Fondy API public key.
     */
    public function getPublicKey();

    /**
     * Get the Fondy API private key.
     *
     * @return string
     *   The Fondy API private key.
     */
    public function getPrivateKey();
}
