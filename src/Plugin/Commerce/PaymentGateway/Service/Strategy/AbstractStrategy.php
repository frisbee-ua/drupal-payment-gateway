<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Strategy;

abstract class AbstractStrategy
{
    const NAME = '';
    const PATTERN_URL = 'https://api.fondy.eu/api/%s/';
    const PATTERN_URL_DEV = 'https://dev2.pay.fondy.eu/api/%s/';
    const PATTERN_URI_CHECKOUT = 'checkout/%s';
    const PATTERN_URI_REVERSE = 'reverse/order_id';
    const PATTERN_SESSION_NAME_CHECKOUT_HASH = 'fondy_checkout_%s_hash';
    const PATTERN_SESSION_NAME_CHECKOUT_CREDENTIALS = 'fondy_checkout_%s_credentials';

    /**
     * @var bool
     */
    private $testMode;

    public function __construct()
    {
        $this->testModeDisable();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return static::NAME;
    }

    /**
     * @return void
     */
    public function testModeEnable()
    {
        $this->testMode = true;
    }

    /**
     * @return void
     */
    public function testModeDisable()
    {
        $this->testMode = false;
    }

    /**
     * @return bool
     */
    public function isTestModeEnabled()
    {
        return $this->testMode === true;
    }

    /**
     * @return bool
     */
    public function isTestModeDisabled()
    {
        return $this->testMode !== true;
    }

    /**
     * @return string
     */
    public function getUrlPattern()
    {
        if ($this->isTestModeEnabled()) {
            return static::PATTERN_URL_DEV;
        }

        return static::PATTERN_URL;
    }

    /**
     * @return string
     */
    public function getRequestUrlCheckout()
    {
        return sprintf($this->getUrlPattern(), sprintf(static::PATTERN_URI_CHECKOUT, static::NAME));
    }

    /**
     * @return string
     */
    public function getRequestUrlReverse()
    {
        return sprintf($this->getUrlPattern(), static::PATTERN_URI_REVERSE);
    }

    /**
     * @return string
     */
    public function getSessionNameCheckoutHash()
    {
        return sprintf(static::PATTERN_SESSION_NAME_CHECKOUT_HASH, static::NAME);
    }

    /**
     * @return string
     */
    public function getSessionNameCheckoutCredentials()
    {
        return sprintf(static::PATTERN_SESSION_NAME_CHECKOUT_CREDENTIALS, static::NAME);
    }

    /**
     * @param $response
     * @return mixed|bool
     */
    public function retrieveCredentialsFromResponse($response)
    {
        if (isset($response->{static::NAME})) {
            return $response->{static::NAME};
        }

        return false;
    }
}
