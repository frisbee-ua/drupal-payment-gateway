<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Configuration;

use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Entity\PaymentType;
use Drupal\commerce_payment\Entity\PaymentGateway;

final class ConfigurationService
{
    /**
     * @var PaymentType
     */
    private $paymentType;

    /**
     * @var array
     */
    private $configuration;

    /**
     * @var \Drupal\commerce_payment\Entity\PaymentGateway
     */
    private $paymentGateway;

    public function __construct()
    {
        $this->paymentType = new PaymentType();
        $this->paymentGateway = PaymentGateway::load('fondy');
        $this->configuration = $this->getPluginConfiguration();
    }

    /**
     * @param array $configuration
     * @return void
     */
    public function setConfiguration(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return array|false
     */
    public function getPluginConfiguration()
    {
        if ($this->paymentGateway) {
            return $this->paymentGateway->getPluginConfiguration();
        }

        return false;
    }

    /**
     * @return \Drupal\commerce_payment\Entity\PaymentGateway|\Drupal\Core\Entity\EntityInterface|null
     */
    public function getPaymentGateway()
    {
        return $this->paymentGateway;
    }

    /**
     * @return bool
     */
    public function isConfigurationTestModeEnabled()
    {
        return $this->getConfigurationValue('mode') === 'test';
    }

    /**
     * @return bool
     */
    public function isConfigurationCardsPaymentEnabled()
    {
        return (bool) $this->getConfigurationValue(['cards_payment_method', 'cards_payment_status']);
    }

    /**
     * @return bool
     */
    public function isConfigurationBankLinksPaymentEnabled()
    {
        return (bool) $this->getConfigurationValue(['bank_links_payment_method', 'bank_links_payment_status']);
    }

    /**
     * @return bool
     */
    public function isConfigurationWalletsPaymentEnabled()
    {
        return (bool) $this->getConfigurationValue(['wallets_payment_method', 'wallets_payment_status']);
    }

    /**
     * @return bool
     */
    public function isConfigurationPreAuthEnabled()
    {
        return (bool) $this->getConfigurationValue('invoice_before_fraud_review');
    }

    /**
     * @return bool
     */
    public function isConfigurationPaymentTypeEmbedded()
    {
        $paymentType = $this->getConfigurationValue('payment_type');

        return (bool) $this->paymentType->isTypeEmbedded($paymentType);
    }

    /**
     * @return bool
     */
    public function isConfigurationPaymentTypeRedirect()
    {
        $paymentType = $this->getConfigurationValue('payment_type');

        return (bool) $this->paymentType->isTypeRedirect($paymentType);
    }

    /**
     * @return string
     */
    public function getOptionMerchantId()
    {
        return trim($this->getPaymentConfigMerchantId());
    }

    /**
     * @return string
     */
    public function getOptionSecretKey()
    {
        return trim($this->getPaymentConfigSecretKey());
    }

    /**
     * @return string
     */
    public function getOptionOrderStatusProcessing()
    {
        return $this->getConfigurationValue('order_status_in_progress');
    }

    /**
     * @return string
     */
    public function getOptionOrderStatusCanceled()
    {
        return $this->getConfigurationValue('order_status_if_canceled');
    }

    /**
     * @return string
     */
    public function getOptionOrderStatusPaid()
    {
        return $this->getConfigurationValue('order_status_pending');
    }

    /**
     * @return mixed
     */
    public function getPaymentConfigMerchantId()
    {
        return $this->getConfigurationValue('merchant_id');
    }

    /**
     * @return mixed
     */
    public function getPaymentConfigSecretKey()
    {
        return $this->getConfigurationValue('private_key');
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->getPaymentConfigMerchantId()) && !empty($this->getOptionSecretKey());
    }

    /**
     * @param string|array $field
     * @return mixed
     */
    public function getConfigurationValue($field)
    {
        if (!is_array($field)) {
            if (!isset($this->configuration[$field])) {
                return null;
            }

            return $this->configuration[$field];
        }

        if (count($field) === 0) {
            return $this->configuration;
        }

        $key = array_shift($field);

        return $this->getValueByPath($this->configuration[$key], $field);
    }

    /**
     * @param $data
     * @param array $path
     * @return mixed
     */
    public function getValueByPath($data, array $path)
    {
        if (count($path) === 0) {
            return $data;
        }

        $key = array_shift($path);

        if (!isset($data[$key])) {
            return null;
        }

        return $this->getValueByPath($data[$key], $path);
    }
}
