<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Builder\RequestBuilder;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Configuration\ConfigurationService;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Entity\Order\Status as OrderStatusEntity;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Entity\PaymentType as PaymentTypeEntity;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Order\OrderManager;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\Manual;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the On-site payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "fondy",
 *   label = "Fondy",
 *   display_label = "Fondy",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_fondy\PluginForm\Fondy\RedirectCheckoutForm",
 *   },
 *   payment_method_types = {"fondy"},
 *   requires_billing_information = FALSE,
 * )
 */
class Fondy extends OffsitePaymentGatewayBase implements FondyInterface
{
    /**
     * @var \Fondy\Fondy
     */
    protected $fondy;

    /**
     * @var \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Builder\RequestBuilder
     */
    private $requestBuilder;

    /**
     * @var \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Configuration\ConfigurationService
     */
    private $configurationService;

    /**
     * @var \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Order\OrderManager
     */
    private $orderManager;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        EntityTypeManagerInterface $entity_type_manager,
        PaymentTypeManager $payment_type_manager,
        PaymentMethodTypeManager $payment_method_type_manager,
        TimeInterface $time,
        RequestBuilder $requestBuilder,
        ConfigurationService $configurationService,
        OrderManager $orderManager
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);
        $this->requestBuilder = $requestBuilder;
        $this->configurationService = $configurationService;
        $this->orderManager = $orderManager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager'),
            $container->get('plugin.manager.commerce_payment_type'),
            $container->get('plugin.manager.commerce_payment_method_type'),
            $container->get('datetime.time'),
            $container->get(RequestBuilder::class),
            $container->get(ConfigurationService::class),
            $container->get(OrderManager::class)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPublicKey()
    {
        return $this->configurationService->getOptionMerchantId();
    }

    /**
     * {@inheritdoc}
     */
    public function getPrivateKey()
    {
        return $this->configurationService->getOptionSecretKey();
    }

    /**
     * Returns payment method title.
     *
     * @return string
     */
    public function getDisplayLabel()
    {
        return ! empty($this->configuration['payment_method_title']) ? $this->configuration['payment_method_title'] : $this->configuration['display_label'];
    }

    /**
     * Returns payment method description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->configuration['payment_method_description'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $paymentType = new PaymentTypeEntity();
        $status = new OrderStatusEntity();

        $form['merchant_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Merchant ID'),
            '#default_value' => $this->configuration['merchant_id'],
            '#required' => true,
        ];
        $form['private_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Private API key'),
            '#default_value' => $this->configuration['private_key'],
            '#required' => true,
        ];
        $form['cards_payment_method'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Credit Cards'),
        ];
        $form['cards_payment_method']['cards_payment_status'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Credit Cards Payments'),
            '#default_value' => $this->configuration['cards_payment_method']['cards_payment_status'],
        ];
        $form['cards_payment_method']['cards_payment_title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Credit Cards Payment Title'),
            '#default_value' => $this->configuration['cards_payment_method']['cards_payment_title'],
        ];

        $form['bank_links_payment_method'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Bank Links'),
        ];
        $form['bank_links_payment_method']['bank_links_payment_method_status'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Bank Links Payments'),
            '#default_value' => $this->configuration['bank_links_payment_method']['bank_links_payment_method_status'],
        ];
        $form['bank_links_payment_method']['bank_links_payment_method_title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Bank Links Payment Title'),
            '#default_value' => $this->configuration['bank_links_payment_method']['bank_links_payment_method_title'],
        ];
        $form['bank_links_payment_method']['bank_links_default_country'] = [
            '#type' => 'address_country',
            '#title' => $this->t('Bank Links Default Country'),
            '#default_value' => $this->configuration['bank_links_payment_method']['bank_links_default_country'],
            '#id' => 'bank_links_default_country',
        ];
        $form['bank_links_payment_method']['bank_links_allowed_countries'] = [
            '#type' => 'select_country',
            '#title' => $this->t('Allowed Bank Links Countries'),
            '#default_value' => $this->configuration['bank_links_payment_method']['bank_links_allowed_countries'],
            '#multiple' => true,
            '#input' => true,
            '#id' => 'bank_links_allowed_countries',
        ];

        $form['wallets_payment_method'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Google/Apple Pay'),
        ];
        $form['wallets_payment_method']['wallets_payment_status'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Google/Apple Pay Payments'),
            '#default_value' => $this->configuration['wallets_payment_method']['wallets_payment_status'],
        ];
        $form['wallets_payment_method']['wallets_payment_title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Google/Apple Pay Payment Title'),
            '#default_value' => $this->configuration['wallets_payment_method']['wallets_payment_title'],
        ];

        $form['enable_preauth'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Holding'),
            '#default_value' => $this->configuration['enable_preauth'],
        ];
        $form['payment_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Payment Type'),
            '#default_value' => $this->configuration['payment_type'],
            '#options' => $paymentType->getConfigurationOptions(),
        ];

        $form['order_status_pending'] = [
            '#type' => 'select',
            '#title' => $this->t('Order status after payment'),
            '#default_value' => $this->configuration['order_status_pending'],
            '#options' => $status->getConfigurationOptions(),
        ];

        $form['order_status_if_canceled'] = [
            '#type' => 'select',
            '#title' => $this->t('Order status if canceled'),
            '#default_value' => $this->configuration['order_status_if_canceled'],
            '#options' => $status->getConfigurationOptions(),
        ];

        $form['order_status_in_progress'] = [
            '#type' => 'select',
            '#title' => $this->t('Order status while in progress'),
            '#default_value' => $this->configuration['order_status_in_progress'],
            '#options' => $status->getConfigurationOptions(),
        ];

        $form['payment_method_title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Payment method title'),
            '#default_value' => $this->configuration['payment_method_title'],
            '#description' => $this->t('The title will appear on checkout page in payment methods list. Leave blank for payment method title.'),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);

        if (! $form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);

            $configurations = [
                'merchant_id',
                'private_key',
                'cards_payment_method',
                'bank_links_payment_method',
                'wallets_payment_method',
                'enable_preauth',
                'payment_type',
                'order_status_pending',
                'order_status_if_canceled',
                'order_status_in_progress',
                'payment_method_title',
            ];

            foreach ($configurations as $option) {
                if (isset($values[$option])) {
                    $this->configuration[$option] = $values[$option];
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createPayment(PaymentInterface $payment, $capture = true)
    {
        return $payment;
    }

    /**
     * {@inheritdoc}
     */
    public function capturePayment(PaymentInterface $payment, Price $amount = null)
    {
        return $payment;
    }

    /**
     * {@inheritdoc}
     */
    public function voidPayment(PaymentInterface $payment)
    {
        return $payment;
    }

    /**
     * {@inheritdoc}
     */
    public function refundPayment(PaymentInterface $payment, Price $amount = null)
    {
        $amount = $amount ?: $payment->getAmount();
        $remote_id = $payment->getRemoteId();
        $order = $payment->getOrder();
        $extOrderId = $order->getOrderNumber();

        try {
            $this->requestBuilder->refund($order, $extOrderId, $amount);

            if ($order->getTotalPrice()->getNumber() <= $payment->getRefundedAmount()->getNumber()) {
                $orderStatus = $this->orderManager->getOrderStatusTotallyRefunded();
                $state = 'refunded';
            } else {
                $orderStatus = $this->orderManager->getOrderStatusPartiallyRefunded();
                $state = 'partially_refunded';
            }

            $payment->setState($state);
            $message = $this->requestBuilder->getStatusMessage();
            $comment = sprintf(
                'Message: %s Frisbee ID: %s',
                $message,
                $extOrderId
            );

            $this->orderManager->addCommentToHistory($order, "${state}_message", $comment);

            if ($this->requestBuilder->isNotSuccessful()) {
                throw new PaymentGatewayException($this->requestBuilder->getErrorMessage());
            }

            $this->orderManager->setStatus($order, $orderStatus);

            $oldRefundedAmount = $payment->getRefundedAmount();
            $newRefundedAmount = $oldRefundedAmount->add($amount);
            $payment->setRefundedAmount($newRefundedAmount);
            $payment->save();
        } catch (\Exception $exception) {
            \Drupal::logger('commerce_fondy')->error($exception->getMessage());

            throw new PaymentGatewayException($this->t('Refund failed. Order @orderId, Transaction @id. @message', [
                '@id' => $remote_id,
                '@orderId' => $order->id(),
                '@message' => $exception->getMessage(),
            ]));
        }
    }

    /**
     * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
     * @return bool
     */
    public function canRefundPayment(PaymentInterface $payment)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function createPaymentMethod(PaymentMethodInterface $payment_method, $payment_details)
    {
        return $payment_method;
    }

    /**
     * {@inheritdoc}
     */
    public function deletePaymentMethod(PaymentMethodInterface $payment_method)
    {
        $payment_method->delete();
    }
}
