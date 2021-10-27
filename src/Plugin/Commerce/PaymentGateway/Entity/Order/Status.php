<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Entity\Order;

use \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Entity\ConfigurationOptionsInterface;

class Status implements ConfigurationOptionsInterface
{
    const ORDER_STATUS_PENDING = 'draft';
    const ORDER_STATUS_PROCESSING = 'processing';
    const ORDER_STATUS_APPROVED = 'approved';
    const ORDER_STATUS_CANCELED = 'canceled';
    const ORDER_STATUS_TOTALLY_REFUNDED = 'refunded';
    const ORDER_STATUS_PARTIALLY_REFUNDED = 'refunded_partial';

    /**
     * @return array
     */
    public function getConfigurationOptions()
    {
        return [
            self::ORDER_STATUS_PENDING => t('Pending'),
            self::ORDER_STATUS_PROCESSING => t('Processing'),
            self::ORDER_STATUS_APPROVED => t('Approved'),
            self::ORDER_STATUS_CANCELED => t('Canceled'),
            self::ORDER_STATUS_TOTALLY_REFUNDED => t('Reversed'),
            self::ORDER_STATUS_PARTIALLY_REFUNDED => t('Partially Reversed'),
        ];
    }
}
