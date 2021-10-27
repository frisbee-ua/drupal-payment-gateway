<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Order;

use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Configuration\ConfigurationService;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Entity\Order\Status as OrderStatusEntity;
use Drupal\commerce_order\Entity\Order;
use Drupal;
use Exception;

final class OrderManager
{
    /**
     * @var \Drupal\Core\DependencyInjection\Container
     */
    private $container;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var OrderStatusEntity
     */
    private $orderStatusEntity;

    public function __construct()
    {
        $this->container = Drupal::getContainer();
        $this->configurationService = $this->container->get(ConfigurationService::class);
        $this->orderStatusEntity = $this->container->get(OrderStatusEntity::class);
    }

    /**
     * @param Order $order
     * @return void
     * @throws \Exception
     */
    public function setOrderStatusProcessing($order)
    {
        $orderStatusProcessing = $this->configurationService->getOptionOrderStatusProcessing();

        $this->setStatus($order, $orderStatusProcessing);
    }

    /**
     * @param Order $order
     * @return void
     */
    public function setOrderStatusCancelled($order)
    {
        $orderStatusCanceled = $this->configurationService->getOptionOrderStatusCanceled();

        $this->setStatus($order, $orderStatusCanceled);
    }

    /**
     * @param Order $order
     * @return void
     */
    public function setOrderStatusPaid($order)
    {
        $orderStatusPaid = $this->configurationService->getOptionOrderStatusPaid();

        $this->setStatus($order, $orderStatusPaid);
    }

    /**
     * @param Order $order
     * @return void
     */
    public function setOrderStatusTotallyRefunded($order)
    {
        $orderStatusTotallyRefunded = $this->getOrderStatusTotallyRefunded();

        $this->setStatus($order, $orderStatusTotallyRefunded);
    }

    /**
     * @param Order $order
     * @return void
     */
    public function setOrderStatusPartiallyRefunded($order)
    {
        $orderStatusPartiallyRefunded = $this->getOrderStatusPartiallyRefunded();

        $this->setStatus($order, $orderStatusPartiallyRefunded);
    }

    /**
     * @return string
     */
    public function getOrderStatusTotallyRefunded()
    {
        return OrderStatusEntity::ORDER_STATUS_TOTALLY_REFUNDED;
    }

    /**
     * @return string
     */
    public function getOrderStatusPartiallyRefunded()
    {
        return OrderStatusEntity::ORDER_STATUS_PARTIALLY_REFUNDED;
    }

    /**
     * @param \Drupal\commerce_order\Entity\Order $order
     * @param string $orderStatus
     * @return void
     * @throws \Drupal\Core\Entity\EntityStorageException
     * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
     */
    public function setStatus(Order $order, string $orderStatus)
    {
        $orderState = $order->getState();

        try {
            $orderState->applyTransitionById($orderStatus);
        } catch (Exception $exception) {
            $orderState->setValue($orderStatus);
        }

        $order->save();
    }

    /**
     * @param $order
     * @param string $type
     * @param string|null $message
     * @return void
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public function addCommentToHistory($order, string $type, string $message = null)
    {
        $parameters = [];

        if ($message) {
            $parameters['message'] = $message;
        }

        $logStorage = \Drupal::entityTypeManager()->getStorage('commerce_log');
        $logStorage->generate($order, $type, $parameters)->save();
    }
}
