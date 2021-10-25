<?php

namespace Drupal\commerce_fondy\PluginForm\Fondy;

use Drupal;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Builder\RequestBuilder;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Configuration\ConfigurationService;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Order\OrderManager;
use Drupal\commerce_payment\Entity\Payment;

/**
 * Class RedirectCheckoutForm.
 *
 * This defines a form that Drupal Commerce will redirect to, when the user
 * clicks the Pay and complete purchase button.
 *
 * This class only needs to implement one method: buildConfigurationForm().
 * However, must first:
 *  - Do anything else you need to do, validate or get auth
 *  - Then submit payment request to the server.
 *
 * @package Drupal\commerce_custom\PluginForm
 */
class RedirectCheckoutForm extends PaymentOffsiteForm
{
    /**
     * @var \Drupal\Core\DependencyInjection\Container
     */
    private $container;

    /**
     * Module log setting.
     */
    private $log;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var RequestBuilder
     */
    private $requestBuilder;

    /**
     * @var OrderManager
     */
    private $orderManager;

    public function __construct()
    {
        $this->container = Drupal::getContainer();
        $this->configurationService = $this->container->get(ConfigurationService::class);
        $this->requestBuilder = $this->container->get(RequestBuilder::class);
        $this->orderManager = $this->container->get(OrderManager::class);
    }

    /**
     * Creates the checkout form.
     *
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);
        
        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $payment = $this->entity;

        if ($payment->getAmount() === null) {
            Drupal::logger('commerce_fondy')->error('Payment total is missing or 0.');

            return Drupal::messenger()->addMessage('Payment total is missing or 0');
        }

        $order = Order::load($payment->getOrderId());

        if ($order === null) {
            Drupal::logger('commerce_fondy')->error('Order ID is missing.');

            return Drupal::messenger()->addMessage('Order ID is missing.');
        }

        $settings = Drupal::config('commerce_fondy.settings');
        $this->log = $settings->get('log');

        if (!$this->configurationService->isConfigured()) {
            Drupal::logger('commerce_fondy')->error('The payment gateway is missing one or more settings.');

            return Drupal::messenger()->addMessage('The payment gateway is not configured properly.');
        }

        $order = $payment->getOrder();
        $orderId = $order->id();
        $order->set('type', 'fondy');

        $this->requestBuilder->setOrderId($orderId);
        $fondyOrderId = $this->requestBuilder->prepare($order);
        $order->setOrderNumber($fondyOrderId);
        $order->save();

        if ($this->configurationService->isConfigurationPaymentTypeRedirect()) {
            $paymentUrl = $this->buildPaymentRequestUrl($payment);

            return $this->buildRedirectForm(
                $form,
                $form_state,
                $paymentUrl,
                [],
                PaymentOffsiteForm::REDIRECT_POST
            );
        }

        $options = $this->requestBuilder->generateCheckoutOptions($order);

        $form['#attached']['drupalSettings']['commerceFondy'] = $options;
        $form['#attached']['library'][] = 'commerce_fondy/form';
        $form['fondyContainer'] = [
            '#type' => 'container',
            '#attributes' => [
                'id' => 'fondy-checkout-container',
            ],
        ];

        return $form;
    }

    /**
     * @param \Drupal\commerce_payment\Entity\Payment $payment
     * @return bool|false|mixed
     * @throws \Exception
     */
    private function buildPaymentRequestUrl(Payment $payment)
    {
        $order = $payment->getOrder();
        $orderId = $order->id();

        $credentials = $this->requestBuilder->retrieveCheckoutCredentials();
        $message = (string) $this->requestBuilder->getErrorMessage();

        if (!empty($message)) {
            $this->orderManager->addCommentToHistory($order, 'error', $message);

            Drupal::logger('commerce_fondy')->error($message);

            return Drupal::messenger()->addMessage($message);
        }

        $order = Order::load($orderId);
        $order->setPlacedTime(\Drupal::time()->getCurrentTime());
        $this->orderManager->setOrderStatusProcessing($order);
        $order->save();

        return $credentials;
    }
}
