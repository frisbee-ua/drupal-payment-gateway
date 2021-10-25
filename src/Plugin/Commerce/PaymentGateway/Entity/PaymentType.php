<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Entity;

class PaymentType implements ConfigurationOptionsInterface
{
    const PAYMENT_TYPE_REDIRECT = 'redirect';
    const PAYMENT_TYPE_EMBEDDED = 'embedded';

    /**
     * @return array
     */
    public function getConfigurationOptions()
    {
        return [
            self::PAYMENT_TYPE_REDIRECT => t('Redirect'),
            self::PAYMENT_TYPE_EMBEDDED => t('Embedded'),
        ];
    }

    /**
     * @param string $paymentType
     * @return bool
     */
    public function isTypeEmbedded($paymentType)
    {
        return $paymentType === self::PAYMENT_TYPE_EMBEDDED;
    }

    /**
     * @param string $paymentType
     * @return bool
     */
    public function isTypeRedirect($paymentType)
    {
        return $paymentType === self::PAYMENT_TYPE_REDIRECT;
    }
}
