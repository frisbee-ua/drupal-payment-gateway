<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Builder;

use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Configuration\ConfigurationService;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\FondyService;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Price;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Exception;

final class RequestBuilder extends Container
{
    const PRECISION = 2;

    /**
     * @var FondyService
     */
    private $fondyService;

    /**
     * @var @string
     */
    private $errorMessage;

    /**
     * @var int
     */
    private $orderId;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var string
     */
    private $paymentMethod;

    /**
     * @var bool
     */
    private $isSuccessful;

    public function __construct(
        FondyService $fondyService,
        ConfigurationService $configurationService
    ) {
        parent::__construct();
        $this->fondyService = $fondyService;
        $this->configurationService = $configurationService;
    }

    public static function create(ContainerInjectionInterface $container)
    {
        return new static(
            $container->get(FondyService::class),
            $container->get(ConfigurationService::class)
        );
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * @param $paymentMethod
     * @return void
     */
    public function setPaymentMethod($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @return mixed
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public function prepare(Order $order)
    {
        if (isset($this->paymentMethod)) {
            $this->fondyService->setStrategyByType($this->paymentMethod);
        } elseif ($this->configurationService->isConfigurationPaymentTypeEmbedded()) {
            $this->fondyService->useStrategyToken();
        } else {
            $this->fondyService->useStrategyUrl();
        }

        if ($this->configurationService->isConfigurationTestModeEnabled()) {
            $this->fondyService->testModeEnable();
        }

        if ($this->configurationService->isConfigurationCardsPaymentEnabled()) {
            $this->fondyService->withPaymentMethodCards();
        }

        if ($this->configurationService->isConfigurationBankLinksPaymentEnabled()) {
            $this->fondyService->withPaymentMethodBankLinks();
            $this->fondyService->setRequestParameterDefaultPaymentSystem(FondyService::PAYMENT_METHOD_BANK_LINKS);
        }

        if ($this->configurationService->isConfigurationWalletsPaymentEnabled()) {
            $this->fondyService->withPaymentMethodWallets();
        }

        $orderId = $order->id();
        $extOrderId = $order->getOrderNumber();

        if (!empty($extOrderId)) {
            $this->fondyService->setRequestParameterOrderId($extOrderId);
        } else {
            $this->fondyService->generateRequestParameterOrderId($orderId);
        }

        $this->fondyService->setMerchantId($this->configurationService->getOptionMerchantId());
        $this->fondyService->setSecretKey($this->configurationService->getOptionSecretKey());
        $this->fondyService->setRequestParameterOrderDescription($this->generateOrderDescription($order));
        $this->fondyService->setRequestParameterAmount($this->getOrderAmount($order));
        $this->fondyService->setRequestParameterCurrency($this->getOrderCurrency($order));
        $this->fondyService->setRequestParameterServerCallbackUrl($this->getCallbackUrl());
        $this->fondyService->setRequestParameterResponseUrl($this->getSuccessPageUrl());
        $this->fondyService->setRequestParameterLanguage($this->getLanguageCode($order));
        $this->fondyService->setRequestParameterSenderEmail($order->getEmail());
        $this->fondyService->setRequestParameterReservationData($this->generateReservationData($order));
        $this->fondyService->setRequestParameterMerchantData($this->generateMerchantData($order));
        $this->fondyService->setRequestUserAgent('Drupal CMS');

        if ($this->configurationService->isConfigurationPreAuthEnabled()) {
            $this->fondyService->enablePreAuthorization();
        }

        return $this->fondyService->getRequestParameterOrderId();
    }

    /**
     * @return bool|false|mixed
     */
    public function retrieveCheckoutCredentials()
    {
        try {
            $this->fondyService->setRequestParameterLifetime(FondyService::CREDENTIALS_LIFETIME);
            $credentials = $this->fondyService->retrieveCheckoutCredentials($this->orderId);

            if ($credentials) {
                return $credentials;
            }

            $this->setErrorMessage($this->fondyService->getStatusMessage());
        } catch (Exception $exception) {
            $this->setErrorMessage($exception->getMessage());
        }

        return false;
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @param $extOrderId
     * @param \Drupal\commerce_price\Price $amount
     * @return object
     * @throws \Exception
     */
    public function refund(Order $order, $extOrderId, Price $amount)
    {
        if ($this->configurationService->isConfigurationTestModeEnabled()) {
            $this->fondyService->testModeEnable();
        }

        $this->fondyService->setRequestParameterOrderId($extOrderId);
        $this->fondyService->setRequestParameterCurrency($amount->getCurrencyCode());
        $this->fondyService->setRequestParameterAmount($amount->getNumber());
        $this->fondyService->setMerchantId($this->configurationService->getOptionMerchantId());
        $this->fondyService->setSecretKey($this->configurationService->getOptionSecretKey());

        $result = $this->fondyService->reverse();

        if (!$result) {
            $this->setErrorMessage($this->fondyService->getStatusMessage());
        }

        return $result;
    }

    /**
     * @return void
     */
    public function markAsSuccessful()
    {
        $this->isSuccessful = true;
    }

    /**
     * @return void
     */
    public function markAsUnsuccessful()
    {
        $this->isSuccessful = false;
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->isSuccessful;
    }

    /**
     * @return bool
     */
    public function isNotSuccessful()
    {
        return !$this->isSuccessful;
    }

    /**
     * @param $errorMessage
     * @return void
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;
        $this->markAsUnsuccessful();
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->fondyService->getStatusMessage();
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @return string
     */
    public function generateOrderDescription(Order $order)
    {
        $description = '';

        /**
         * @var \Drupal\commerce_order\Entity\OrderItem $item
         */
        foreach ($order->getItems() as $item) {
            $price = $item->getUnitPrice()->getNumber();
            $amount = $item->getAdjustedTotalPrice()->getNumber();
            $description .= sprintf('Name: %s ', $item->getTitle());
            $description .= sprintf('Price: %s ', $this->formatNumberPrecision($price));
            $description .= sprintf('Qty: %s ', $this->formatNumberPrecision($item->getQuantity()));
            $description .= sprintf("Amount: %s\n", $this->formatNumberPrecision($amount));
        }

        return $description;
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @return array
     */
    public function generateReservationData(Order $order)
    {
        $profile = $order->getBillingProfile()->toArray();
        $address = $profile['address'][0];
        $street = trim($address['address_line1'] . ' ' . $address['address_line2']);
        $name = trim($address['given_name'] . ' ' . $address['family_name']);

        $reservationData = array(
            'customer_address' => $street,
            'customer_country' => $address['country_code'],
            'customer_state' => $address['administrative_area'],
            'customer_name' => $name,
            'customer_city' => $address['locality'],
            'customer_zip' => $address['postal_code'],
            'account' => $order->getCustomerId(),
            'products' => $this->generateProductsParameter($order),
            'cms_name' => 'Drupal',
            'cms_version' => \Drupal::VERSION,
            'shop_domain' => $_SERVER['HTTP_HOST'] ?: $_SERVER['SERVER_NAME'],
            'path' => $_SERVER['REQUEST_URI']
        );

        $reservationData['uuid'] = sprintf('%s_%s', $reservationData['shop_domain'], $reservationData['cms_name']);

        return $reservationData;
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @return array
     */
    public function generateProductsParameter(Order $order)
    {
        $products = [];

        /**
         * @var \Drupal\commerce_order\Entity\OrderItem $item
         */
        foreach ($order->getItems() as $item) {
            $products[] = [
                'id' => $item->getPurchasedEntityId(),
                'name' => $item->getTitle(),
                'price' => number_format((float) $item->getUnitPrice()->getNumber(), self::PRECISION),
                'total_amount' => number_format((float) $item->getTotalPrice()->getNumber(), self::PRECISION),
                'quantity' => number_format((float) $item->getQuantity(), self::PRECISION),
            ];
        }

        return $products;
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @return array
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public function generateMerchantData(Order $order)
    {
        $entityManager = \Drupal::entityTypeManager();

        /**
         * @var \Drupal\commerce_store\Entity\Store $store
         */
        $store = $entityManager->getStorage('commerce_store')->loadDefault();
        $data = $store->toArray();
        $merchantData = [
            'fullname' => $store->getName(),
            'email' => $store->getEmail(),
        ];

        if (isset($data['address'][0])){
            $additional = [
                'country_code' => $data['address'][0]['country_code'],
                'administrative_area' => $data['address'][0]['administrative_area'],
                'locality' => $data['address'][0]['locality'],
                'address_line1' => $data['address'][0]['address_line1'],
            ];
            $merchantData = array_merge($merchantData, $additional);
        }

        return $merchantData;
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @param null $token
     * @return string
     * @throws \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Exception\Json\EncodeJsonException
     */
    public function generateCheckoutOptions(Order $order, $token = null)
    {
        $bankLinksDefaultCountry = $this->configurationService->getConfigurationValue(['bank_links_payment_method', 'bank_links_default_country']);
        $countries = $this->configurationService->getConfigurationValue(['bank_links_payment_method', 'specificcountry']);
        $titleCards = $this->configurationService->getConfigurationValue(['cards_payment_method', 'cards_payment_title']);
        $titleBankLinks = $this->configurationService->getConfigurationValue(['bank_links_payment_method', 'bank_links_payment_title']);
        $titleWallets = $this->configurationService->getConfigurationValue(['wallets_payment_method', 'wallets_payment_title']);
        $title = $this->configurationService->getConfigurationValue('title');
        $languageCode = $this->getLanguageCode($order);

        if ($titleCards && $this->configurationService->isConfigurationCardsPaymentEnabled()) {
            $this->fondyService->setCardsTitle($languageCode, $titleCards);
        }

        if ($this->configurationService->isConfigurationBankLinksPaymentEnabled()) {
            if ($titleBankLinks) {
                $this->fondyService->setBankLinksTitle($languageCode, $titleBankLinks);
            }

            if ($countries) {
                if (!is_array($countries)) {
                    if (strpos($countries, ',') !== false) {
                        $countries = explode(',', $countries);
                    } else {
                        $countries = [$countries];
                    }
                }

                $this->fondyService->setRequestParameterCountries($countries);
            }

            if ($bankLinksDefaultCountry) {
                $this->fondyService->setRequestParameterDefaultCountry($bankLinksDefaultCountry);
            }
        }

        if ($titleWallets && $this->configurationService->isConfigurationWalletsPaymentEnabled()) {
            $this->fondyService->setWalletsTitle($languageCode, $titleWallets);
        }

        if ($title) {
            $this->fondyService->setRequestParameterTitle($title);
        }

        return $this->fondyService->getCheckoutOptions();
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @return \Drupal\commerce_price\Price|null
     */
    private function getOrderAmount(Order $order)
    {
        return $order->getTotalPrice();
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @return string
     */
    private function getOrderCurrency(Order $order)
    {
        return $order->getTotalPrice()->getCurrencyCode();
    }

    /**
     * @return \Drupal\Core\GeneratedUrl|string
     */
    private function getSuccessPageUrl()
    {
        return Url::fromRoute('commerce_fondy.finish', [], ['absolute' => TRUE])->toString();
    }

    /**
     * @return \Drupal\Core\GeneratedUrl|string
     */
    private function getCallbackUrl()
    {
        return Url::fromRoute('commerce_fondy.notify', [], ['absolute' => TRUE])->toString();
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @return string
     */
    private function getLanguageCode(Order $order)
    {
        $languageCode = $order->getCustomer()->getPreferredLangcode(false);

        if ($languageCode) {
            return $languageCode;
        }

        return \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    /**
     * @param int $number
     * @param int $precision
     * @return string
     */
    private function formatNumberPrecision($number, $precision = self::PRECISION)
    {
        return number_format($number, $precision);
    }
}
