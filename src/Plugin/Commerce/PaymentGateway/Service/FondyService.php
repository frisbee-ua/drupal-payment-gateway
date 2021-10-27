<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service;

use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Exception\Json\DecodeJsonException;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Exception\Json\EncodeJsonException;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Manager\SessionManager;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Manager\JsonManager;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Strategy\TokenStrategy;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Strategy\UrlStrategy;
use Drupal\commerce_price\Price;
use Exception;

class FondyService
{
    const ORDER_APPROVED = 'approved';
    const ORDER_DECLINED = 'declined';
    const ORDER_REVERSED = 'reversed';
    const ORDER_EXPIRED = 'expired';
    const ORDER_PROCESSING = 'processing';
    const ORDER_SEPARATOR = '#';
    const SIGNATURE_SEPARATOR = '|';

    const PAYMENT_METHOD_CARDS = 'card';
    const PAYMENT_METHOD_BANK_LINKS = 'banklinks_eu';
    const PAYMENT_METHOD_WALLETS = 'wallets';

    const CREDENTIALS_LIFETIME = 3600;

    /**
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * @var JsonManager
     */
    protected $jsonManager;

    /**
     * @var \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Strategy\AbstractStrategy
     */
    protected $strategy;

    /**
     * @var int
     */
    protected $merchantId;

    /**
     * @var string
     */
    protected $secretKey;

    /**
     * @var array
     */
    protected $requestParameters = [];

    /**
     * @var object
     */
    protected $requestResult;

    /**
     * @var string
     */
    protected $requestUserAgent;

    /**
     * @var string
     */
    protected $statusMessage;

    /**
     * @var bool
     */
    protected $isOrderApproved;

    /**
     * @var bool
     */
    protected $isOrderDeclined;

    /**
     * @var bool
     */
    protected $isOrderFullyReversed;

    /**
     * @var bool
     */
    protected $isOrderPartiallyReversed;

    /**
     * @var bool
     */
    protected $isOrderExpired;

    public function __construct()
    {
        $this->sessionManager = new SessionManager();
        $this->jsonManager = new JsonManager();
        $this->useStrategyUrl();
        $this->setRequestUserAgent('CMS/client');
    }

    /**
     * @param \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Strategy\AbstractStrategy $strategy
     * @return void
     */
    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;
    }

    /**
     * @return \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Strategy\AbstractStrategy
     */
    public function getStrategy()
    {
        return $this->strategy;
    }

    /**
     * @return void
     */
    public function useStrategyUrl()
    {
        $this->setStrategy(new UrlStrategy());
    }

    /**
     * @return void
     */
    public function useStrategyToken()
    {
        $this->setStrategy(new TokenStrategy());
    }

    /**
     * @param string $type
     * @return void
     */
    public function setStrategyByType($type)
    {
        if ($type === 'redirect') {
            $this->useStrategyUrl();
        } else {
            $this->useStrategyToken();
        }
    }

    /**
     * @param $merchantId
     * @return void
     */
    public function setMerchantId($merchantId)
    {
        $this->merchantId = trim($merchantId);
        $this->requestParameters['merchant_id'] = $this->merchantId;
    }

    /**
     * @return int
     */
    public function getMerchantId()
    {
        return $this->merchantId;
    }

    /**
     * @param $secretKey
     * @return void
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = trim($secretKey);
    }

    /**
     * @return string
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * @param array $requestParameters
     * @return void
     */
    public function setRequestParameters(array $requestParameters)
    {
        $this->requestParameters = $requestParameters;
    }

    /**
     * @return mixed
     */
    public function getRequestParameters()
    {
        return $this->requestParameters;
    }

    /**
     * @param mixed $orderId
     * @return void
     */
    public function generateRequestParameterOrderId($orderId)
    {
        $this->requestParameters['order_id'] = $orderId . self::ORDER_SEPARATOR . time();
    }

    /**
     * @param mixed $orderId
     * @return void
     */
    public function setRequestParameterOrderId($orderId)
    {
        $this->requestParameters['order_id'] = $orderId;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterOrderId()
    {
        return $this->requestParameters['order_id'];
    }

    /**
     * @param $orderDescription
     * @return void
     */
    public function setRequestParameterOrderDescription($orderDescription)
    {
        $this->requestParameters['order_desc'] = $orderDescription;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterOrderDescription()
    {
        return $this->requestParameters['order_desc'];
    }

    /**
     * @param $amount
     * @return void
     */
    public function setRequestParameterAmount($amount)
    {
        $this->requestParameters['amount'] = $this->getAmount($amount);
    }

    /**
     * @return mixed
     */
    public function getRequestParameterAmount()
    {
        return $this->requestParameters['amount'];
    }

    /**
     * @param $currency
     * @return void
     */
    public function setRequestParameterCurrency($currency)
    {
        $this->requestParameters['currency'] = $currency;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterCurrency()
    {
        return $this->requestParameters['currency'];
    }

    /**
     * @param $callbackUrl
     * @return void
     */
    public function setRequestParameterServerCallbackUrl($callbackUrl)
    {
        $this->requestParameters['server_callback_url'] = $callbackUrl;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterServerCallbackUrl()
    {
        return $this->requestParameters['server_callback_url'];
    }

    /**
     * @param $responseUrl
     * @return void
     */
    public function setRequestParameterResponseUrl($responseUrl)
    {
        $this->requestParameters['response_url'] = $responseUrl;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterResponseUrl()
    {
        return $this->requestParameters['response_url'];
    }

    /**
     * @param $language
     * @return void
     */
    public function setRequestParameterLanguage($language)
    {
        $this->requestParameters['lang'] = $language;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterLanguage()
    {
        return $this->requestParameters['lang'];
    }

    /**
     * @param string $email
     * @return void
     */
    public function setRequestParameterSenderEmail($email)
    {
        $this->requestParameters['sender_email'] = $email;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterSenderEmail()
    {
        return $this->requestParameters['sender_email'];
    }

    /**
     * @param array|string $reservationData
     * @return void
     */
    public function setRequestParameterReservationData($reservationData)
    {
        try {
            if (is_array($reservationData)) {
                $reservationData = base64_encode($this->jsonManager->encode($reservationData));
            }
        } catch (Exception $exception) {
            $reservationData = '';
        }

        $this->requestParameters['reservation_data'] = $reservationData;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterReservationData()
    {
        return $this->requestParameters['reservation_data'];
    }

    /**
     * @param array|string $merchantData
     * @return void
     */
    public function setRequestParameterMerchantData($merchantData)
    {
        try {
            if (is_array($merchantData)) {
                $merchantData = $this->jsonManager->encode($merchantData);
            }
        } catch (Exception $exception) {
            $merchantData = '';
        }

        $this->requestParameters['merchant_data'] = $merchantData;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterMerchantData()
    {
        return $this->requestParameters['merchant_data'];
    }

    /**
     * @param string|array $paymentSystems
     * @return void
     */
    public function setRequestParameterPaymentSystems($paymentSystems)
    {
        $this->requestParameters['payment_systems'] = $paymentSystems;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterPaymentSystems()
    {
        return $this->requestParameters['payment_systems'];
    }

    /**
     * @param string $defaultPaymentSystem
     * @return void
     */
    public function setRequestParameterDefaultPaymentSystem(string $defaultPaymentSystem)
    {
        $this->requestParameters['default_payment_system'] = $defaultPaymentSystem;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterDefaultPaymentSystem()
    {
        return $this->requestParameters['default_payment_system'];
    }

    /**
     * @param string $defaultCountry
     * @return void
     */
    public function setRequestParameterDefaultCountry(string $defaultCountry)
    {
        $this->requestParameters['default_country'] = $defaultCountry;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterDefaultCountry()
    {
        return $this->requestParameters['default_country'];
    }

    /**
     * @param array $countries
     * @return void
     */
    public function setRequestParameterCountries(array $countries)
    {
        $this->requestParameters['countries'] = $countries;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterCountries()
    {
        return $this->requestParameters['countries'];
    }

    /**
     * @param string $title
     * @return void
     */
    public function setRequestParameterTitle($title)
    {
        $this->requestParameters['title'] = $title;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterTitle()
    {
        return $this->requestParameters['title'];
    }

    /**
     * @param string $language
     * @param string $field
     * @param string $message
     * @return void
     */
    public function setRequestParameterLanguageMessage($language, $field, $message)
    {
        if (!isset($this->requestParameters['messages'])) {
            $this->requestParameters['messages'] = [];
        }

        $this->requestParameters['messages'][$language][$field] = $message;
    }

    /**
     * @param $language
     * @param $field
     * @return string|null
     */
    public function getRequestParameterLanguageMessage($language, $field)
    {
        if (isset($this->requestParameters['messages'][$language][$field])) {
            return (string) $this->requestParameters['messages'][$language][$field];
        }

        return null;
    }

    /**
     * @param int $lifetime
     * @return void
     */
    public function setRequestParameterLifetime(int $lifetime)
    {
        $this->requestParameters['lifetime'] = $lifetime;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterLifetime()
    {
        return $this->requestParameters['lifetime'];
    }

    /**
     * @param string $comment
     * @return void
     */
    public function setRequestParameterComment($comment)
    {
        $this->requestParameters['comment'] = $comment;
    }

    /**
     * @return mixed
     */
    public function getRequestParameterComment()
    {
        return $this->requestParameters['comment'];
    }

    /**
     * @param string $language
     * @param string $message
     * @return void
     */
    public function setCardsTitle($language, $message)
    {
        $this->setRequestParameterLanguageMessage($language, self::PAYMENT_METHOD_CARDS, $message);
    }

    /**
     * @param string $language
     * @param string $message
     * @return void
     */
    public function setBankLinksTitle($language, $message)
    {
        $this->setRequestParameterLanguageMessage($language, self::PAYMENT_METHOD_BANK_LINKS, $message);
    }

    /**
     * @param string $language
     * @param string $message
     * @return void
     */
    public function setWalletsTitle($language, $message)
    {
        $this->setRequestParameterLanguageMessage($language, self::PAYMENT_METHOD_WALLETS, $message);
    }

    /**
     * @return void
     */
    public function enablePreAuthorization()
    {
        $this->requestParameters['preauth'] = 'Y';
    }

    /**
     * @return void
     */
    public function disablePreAuthorization()
    {
        $this->requestParameters['preauth'] = 'N';
    }

    /**
     * @return void
     */
    public function withPaymentMethodCards()
    {
        $this->requestParameters['payment_systems'][] = self::PAYMENT_METHOD_CARDS;
    }

    /**
     * @return void
     */
    public function withPaymentMethodBankLinks()
    {
        $this->requestParameters['payment_systems'][] = self::PAYMENT_METHOD_BANK_LINKS;
    }

    /**
     * @return void
     */
    public function withPaymentMethodWallets()
    {
        $this->requestParameters['payment_systems'][] = self::PAYMENT_METHOD_WALLETS;
    }

    /**
     * @param $userAgent
     * @return void
     */
    public function setRequestUserAgent($userAgent)
    {
        $this->requestUserAgent = $userAgent;
    }

    /**
     * @return mixed
     */
    public function getRequestResult()
    {
        return $this->requestResult;
    }

    /**
     * @return void
     */
    public function testModeEnable()
    {
        $this->strategy->testModeEnable();
    }

    /**
     * @return array
     */
    public function prepareRequestParameters()
    {
        $requestParameters = $this->requestParameters;

        if (isset($requestParameters['payment_systems'])) {
            $requestParameters['payment_systems'] = $this->joinArrayToStringThroughComma($requestParameters['payment_systems']);
        }

        if (isset($requestParameters['countries'])) {
            $requestParameters['countries'] = $this->joinArrayToStringThroughComma($requestParameters['countries']);
        }

        return $requestParameters;
    }

    /**
     * @param bool $assoc
     * @return array|mixed
     * @throws \Exception
     */
    public function getCallbackData($assoc = true)
    {
        $content = file_get_contents('php://input');

        if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            return $this->jsonManager->decode($content, $assoc);
        }

        if (function_exists('simplexml_load_string') && strpos($_SERVER['CONTENT_TYPE'], 'application/xml') !== false) {
            $object = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($assoc) {
                return (array) $object;
            }

            return $object;
        }

        return $_REQUEST;
    }

    /**
     * @param Price|float|int $value
     * @return float
     */
    public function getAmount($value)
    {
        if ($value instanceof Price) {
            $value = $value->getNumber();
        }

        return (float) $value * 100;
    }

    /**
     * @param $callbackData
     * @return mixed|string
     */
    public function parseOrderId($callbackData)
    {
        list($orderId, $time) = explode(self::ORDER_SEPARATOR, $callbackData['order_id']);

        return $orderId;
    }

    /**
     * @param $orderId
     * @return false|mixed
     * @throws \Exception
     */
    public function retrieveCheckoutCredentials($orderId)
    {
        $requestParameters = $this->prepareRequestParameters();
        $uniqueHash = $this->generateCheckoutUniqueHash($orderId, $requestParameters);

        if ($this->isCheckoutSessionUnique($uniqueHash)) {
            $checkoutCredentials = $this->requestCheckoutCredentials();
        } else {
            $checkoutCredentials = $this->getSessionCheckoutCredentials();
        }

        if (!empty($checkoutCredentials)) {
            if (!$this->getSessionCheckoutHash()) {
                $this->setSessionCheckoutHash($uniqueHash);
                $this->setSessionCheckoutCredentials($checkoutCredentials);
            }

            return $checkoutCredentials;
        }

        return $this->requestCheckoutCredentials();
    }

    /**
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function handleCallbackData($data)
    {
        if (!$this->isCallbackDataValid($data)) {
            return false;
        }

        $orderStatus = strtolower($data['order_status']);

        if ($orderStatus === self::ORDER_EXPIRED) {
            $this->isOrderExpired = true;
            $this->setStatusMessage('Order was expired.');

            return false;
        }

        if ($orderStatus === self::ORDER_REVERSED) {
            $this->isOrderFullyReversed = true;
            $this->setStatusMessage('Order was fully reversed.');

            return true;
        }

        if (isset($data['reversal_amount']) && $data['reversal_amount'] > 0) {
            $this->isOrderPartiallyReversed = true;
            $this->setStatusMessage('Order was partially reversed.');

            return true;
        }

        if ($orderStatus === self::ORDER_DECLINED || ($orderStatus === self::ORDER_PROCESSING && empty($response['actual_amount']))) {
            $this->isOrderDeclined = true;
            $this->setStatusMessage('Order was declined.');

            return false;
        }

        if ($orderStatus === self::ORDER_APPROVED) {
            $this->isOrderApproved = true;
            $this->setStatusMessage('Order was approved.');

            return true;
        }

        $this->isOrderApproved = false;
        $this->setStatusMessage('Order was not approved.');

        return false;
    }

    /**
     * @return mixed
     */
    public function isOrderDeclined()
    {
        return $this->isOrderDeclined;
    }

    /**
     * @return mixed
     */
    public function isOrderFullyReversed()
    {
        return $this->isOrderFullyReversed;
    }

    /**
     * @return mixed
     */
    public function isOrderPartiallyReversed()
    {
        return $this->isOrderPartiallyReversed;
    }

    /**
     * @return mixed
     */
    public function isOrderApproved()
    {
        return $this->isOrderApproved;
    }

    /**
     * @return mixed
     */
    public function isOrderExpired()
    {
        return $this->isOrderExpired;
    }

    /**
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function isCallbackDataValid($data)
    {
        if (!isset($data['order_status'])) {
            throw new Exception('Callback data order_status is empty.');
        }

        if (!isset($data['merchant_id'])) {
            throw new Exception('Callback data merchant_id is empty.');
        }

        if (!isset($data['signature'])) {
            throw new Exception('Callback data signature is empty.');
        }

        if ($this->merchantId != $data['merchant_id']) {
            throw new Exception('An error has occurred during payment. Merchant data is incorrect.');
        }

        $responseSignature = $data['signature'];
        if (isset($data['response_signature_string'])) {
            unset($data['response_signature_string']);
        }

        if (isset($data['signature'])) {
            unset($data['signature']);
        }

        if ($this->getSignature($data) !== $responseSignature) {
            //throw new Exception('Signature is not valid.');
        }

        return true;
    }

    /**
     * @param string $hash
     * @param int|null $expire
     * @return void
     */
    public function setSessionCheckoutHash($hash, $expire = null)
    {
        if (!$expire) {
            $expire = self::CREDENTIALS_LIFETIME;
        }

        $sessionNameCheckoutHash = $this->strategy->getSessionNameCheckoutHash();

        $this->sessionManager->set($sessionNameCheckoutHash, $hash, $expire);
    }

    /**
     * @param string $credentials
     * @param int|null $expire
     * @return void
     */
    public function setSessionCheckoutCredentials($credentials, $expire = null)
    {
        if (!$expire) {
            $expire = self::CREDENTIALS_LIFETIME;
        }

        $sessionNameCheckoutCredentials = $this->strategy->getSessionNameCheckoutCredentials();

        $this->sessionManager->set($sessionNameCheckoutCredentials, $credentials, $expire);
    }

    /**
     * @return mixed|null
     */
    public function getSessionCheckoutHash()
    {
        $sessionNameCheckoutHash = $this->strategy->getSessionNameCheckoutHash();

        if ($this->sessionManager->has($sessionNameCheckoutHash)) {
            return $this->sessionManager->get($sessionNameCheckoutHash);
        }

        return null;
    }

    /**
     * @return mixed|null
     */
    public function getSessionCheckoutCredentials()
    {
        $sessionNameCheckoutCredentials = $this->strategy->getSessionNameCheckoutCredentials();

        if ($this->sessionManager->has($sessionNameCheckoutCredentials)) {
            return $this->sessionManager->get($sessionNameCheckoutCredentials);
        }

        return null;
    }

    /**
     * @param $hash
     * @return bool
     */
    public function isCheckoutSessionUnique($hash)
    {
        return $this->getSessionCheckoutHash() !== $hash;
    }

    /**
     * @return mixed
     */
    public function getStatusMessage()
    {
        return $this->statusMessage;
    }

    /**
     * @return array
     */
    public function getPaymentMethods()
    {
        return [
            self::PAYMENT_METHOD_CARDS,
            self::PAYMENT_METHOD_BANK_LINKS,
            self::PAYMENT_METHOD_WALLETS,
        ];
    }

    /**
     * @param string
     * @return string
     * @throws \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Exception\Json\EncodeJsonException
     */
    public function getCheckoutOptions($token = null)
    {
        $options = [
            'fields' => false,
            'full_screen' => false,
            'button' => true,
        ];

        if (isset($this->requestParameters['payment_systems'])) {
            $paymentMethods = $this->requestParameters['payment_systems'];
            if (!is_array($paymentMethods)) {
                $paymentMethods = [$paymentMethods];
            }

            $options['methods'] = $paymentMethods;
        } else {
            $options['methods'] = [self::PAYMENT_METHOD_CARDS];
        }

        if (isset($this->requestParameters['default_payment_system']) &&
            $this->requestParameters['default_payment_system'] === self::PAYMENT_METHOD_BANK_LINKS
        ) {
            $options['active_tab'] = $this->requestParameters['default_payment_system'];
        }

        if (isset($this->requestParameters['default_country'])) {
            $options['default_country'] = $this->requestParameters['default_country'];
        }

        if (isset($this->requestParameters['countries'])) {
            $options['countries'] = $this->requestParameters['countries'];
        }

        if (isset($this->requestParameters['title'])) {
            $options['title'] = $this->requestParameters['title'];
        }

        if (isset($this->requestParameters['lang'])) {
            $options['locales'] = [$this->requestParameters['lang']];
        }

        $params = $this->getRequestParameters();

        $params['merchant_id'] = (int) $params['merchant_id'];

        if ($token) {
            $params['token'] = $token;
            $params = array_diff_key($params, array_flip(['amount', 'currency']));
        }

        $options = [
            'options' => $options,
            'params' => $params
        ];

        if (isset($this->requestParameters['messages'])) {
            $options['messages'] = $this->requestParameters['messages'];
        }

        return $this->jsonManager->encode($options);
    }

    /**
     * @param string $url
     * @param array $requestParameters
     * @param string $method
     * @return bool
     */
    public function request(string $url, array $requestParameters, $method = 'POST')
    {
        try {
            $requestParameters['signature'] = $this->getSignature($requestParameters, $this->secretKey);

            $contentType = 'application/json';
            $header = sprintf('Content-Type: %s; Accept: %s; User-Agent: %s', $contentType, $contentType, $this->requestUserAgent);
            $content = $this->jsonManager->encode(['request' => $requestParameters]);
            $opts = [
                'http' => [
                    'method' => $method,
                    'header' => $header,
                    'content' => $content
                ]
            ];

            $context = stream_context_create($opts);
            $content = file_get_contents($url, false, $context);
            $this->requestResult = $this->jsonManager->decode($content);

            if (isset($this->requestResult->response->error_message)) {
                $this->setStatusMessage((string) $this->requestResult->response->error_message);
                return false;
            }

            if (!isset($this->requestResult->response, $this->requestResult->response->response_status) ||
                $this->requestResult->response->response_status !== 'success'
            ) {
                $this->setStatusMessage('Fondy response error. Contact Fondy support.');
                return false;
            }
        } catch (EncodeJsonException $encodeJsonException) {
            $this->setStatusMessage($encodeJsonException->getMessage());
        } catch (DecodeJsonException $decodeJsonException) {
            $this->setStatusMessage($decodeJsonException->getMessage());
        } catch (Exception $exception) {
            $this->setStatusMessage(sprintf('%s %s', 'Unable to make request to the Fondy API.', $exception->getMessage()));
        }

        return false;
    }

    /**
     * @return object|false
     */
    public function reverse()
    {
        try {
            $requestParameters = $this->prepareRequestParameters();
            $url = $this->strategy->getRequestUrlReverse();
            $result = $this->request($url, $requestParameters);

            if ($result) {
                return $this->requestResult;
            }
        } catch (Exception $exception) {
            $this->setStatusMessage(sprintf('%s %s', 'Unable to make reverse request to the Fondy API.', $exception->getMessage()));
        }

        return false;
    }

    /**
     * @param $message
     * @return void
     */
    protected function setStatusMessage($message)
    {
        $this->statusMessage = $message;
    }

    /**
     * @return bool|string
     * @throws \Exception
     */
    protected function requestCheckoutCredentials()
    {
        try {
            $requestParameters = $this->prepareRequestParameters();

            $this->request($this->strategy->getRequestUrlCheckout(), $requestParameters);

            return $this->strategy->retrieveCredentialsFromResponse($this->requestResult->response);
        } catch (Exception $exception) {
            $this->setStatusMessage(sprintf('%s %s', 'Unable to make checkout request to the Fondy API.', $exception->getMessage()));
        }

        return false;
    }

    /**
     * @param $orderId
     * @param array $requestParameters
     * @return string
     */
    protected function generateCheckoutUniqueHash($orderId, $requestParameters)
    {
        $uniqueParameters = [
            'merchant_id',
            'order_desc',
            'amount',
            'currency',
            'server_callback_url',
            'response_url',
            'lang',
            'sender_email',
            'payment_systems',
            'reservation_data'
        ];

        $uniqueHash = $orderId;
        foreach ($uniqueParameters as $parameter) {
            $uniqueHash .= $requestParameters[$parameter];
        }

        return md5($uniqueHash);
    }

    /**
     * @param $data
     * @param bool $encoded
     * @return string
     */
    protected function getSignature($data, $encoded = true)
    {
        $data = array_filter($data, function ($var) {
            return $var !== '' && $var !== null;
        });
        ksort($data);

        $str = $this->secretKey;
        foreach ($data as $value) {
            if (is_array($value)) {
                try {
                    $value = $this->jsonManager->encode($value, JSON_HEX_APOS);
                    $value = str_replace('"', "'", $value);
                } catch (EncodeJsonException $encodeJsonException) {
                    $value = (string) $value;
                }
            }

            $str .= self::SIGNATURE_SEPARATOR.$value;
        }

        if ($encoded) {
            return sha1($str);
        }

        return $str;
    }

    /**
     * @param array $array
     * @return string
     */
    protected function joinArrayToStringThroughComma(array $array)
    {
        if (is_array($array)) {
            return implode(',', $array);
        }

        return (string) $array;
    }
}
