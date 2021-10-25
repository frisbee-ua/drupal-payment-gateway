<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Handler;

use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Configuration\ConfigurationService;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Order\OrderManager;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\FondyService;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\Entity\Payment;
use Drupal;
use Exception;

final class CallbackHandler
{
    /**
     * @var \Drupal\Core\DependencyInjection\Container
     */
    private $container;

    /**
     * @var FondyService
     */
    private $fondyService;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var OrderManager
     */
    private $orderManager;

    public function __construct()
    {
        $this->container = Drupal::getContainer();
        $this->fondyService = $this->container->get(FondyService::class);
        $this->configurationService = $this->container->get(ConfigurationService::class);
        $this->orderManager = $this->container->get(OrderManager::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function execute()
    {
        $orderStatusProcessing = $this->configurationService->getOptionOrderStatusProcessing();

        try {
            $data = $this->fondyService->getCallbackData();
            $orderId = $this->fondyService->parseOrderId($data);
            /**
             * @var Order $order
             */
            $order = Order::load($orderId);
            $order->set('cart', false);

            $this->fondyService->setMerchantId($this->configurationService->getOptionMerchantId());
            $this->fondyService->setSecretKey($this->configurationService->getOptionSecretKey());

            $this->fondyService->handleCallbackData($data);

            if ($this->fondyService->isOrderDeclined()) {
                $state = 'voided';
                $orderStatus = $this->configurationService->getOptionOrderStatusCanceled();
            } elseif ($this->fondyService->isOrderApproved()) {
                $state = 'completed';
                $this->createTransaction($order, $data['payment_id'], $order->getTotalPrice(), $state);
                $orderStatus = $this->configurationService->getOptionOrderStatusPaid();
            } elseif ($this->fondyService->isOrderFullyReversed()) {
                $state = 'refunded';
                $this->createTransaction($order, $data['payment_id'], $order->getTotalPrice(), $state);
                $orderStatus = $this->orderManager->getOrderStatusTotallyRefunded();
            } elseif ($this->fondyService->isOrderPartiallyReversed()) {
                $state = 'partially_refunded';
                $this->createTransaction($order, $data['payment_id'], $data['reversal_amount'], $state);
                $orderStatus = $this->orderManager->getOrderStatusPartiallyRefunded();
            } else {
                exit;
            }

            $message = $this->fondyService->getStatusMessage();
        } catch (Exception $exception) {
            $orderStatus = $orderStatusProcessing;
            $message = $exception->getMessage();
            \Drupal::logger('commerce_fondy')->error($message);
            http_response_code(500);
            if (isset($order)) {
                $this->orderManager->setStatus($order, $orderStatus);
            }
        }

        $comment = sprintf(
            'Message: %s Frisbee ID: %s Payment ID: %s',
            $message,
            isset($data['order_id']) ? $data['order_id'] : '',
            isset($data['payment_id']) ? $data['payment_id'] : ''
        );

        $this->orderManager->setStatus($order, $orderStatus);
        $this->orderManager->addCommentToHistory($order, "${state}_message", $comment);

        return isset($exception) ? $exception->getMessage() : $message;
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @param $paymentId
     * @param $amount
     * @param $state
     * @return void
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    private function createTransaction(Order $order, $paymentId, $amount, $state)
    {
        $paymentGateway = $this->configurationService->getPaymentGateway();
        $customer = $order->getCustomerId();

        $entity_type_manager = \Drupal::entityTypeManager();
        $storage = $entity_type_manager->getStorage('commerce_payment');
        $result = $storage->loadByProperties([
            'remote_id' => $order->getOrderNumber(),
        ]);

        if (is_array($result)) {
            $payment = array_shift($result);
        }

        if (empty($payment)) {
            $payment = Payment::create([
                'id' => $paymentId,
                'state' => $state,
                'amount' => $amount,
                'payment_gateway' => $paymentGateway->id(),
                'order_id' => $order->id(),
                'remote_id' => $order->getOrderNumber(),
                'payment_gateway_mode' => $paymentGateway->getPlugin()->getMode(),
                'expires' => 0,
                'uid' => $customer,
            ]);
        }

        if ($state === 'refunded' || $state === 'partially_refunded') {
            $payment->setRefundedAmount($amount);
        }

        $payment->save();
    }
}
