<?php

namespace Drupal\commerce_fondy\Controller;

use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Handler\CallbackHandler;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal;

/**
 * Endpoints for the routes defined.
 */
class ResultController extends Drupal\commerce_payment\Controller\PaymentCheckoutController
{
    /**
     * @var \Drupal\Core\DependencyInjection\Container
     */
    private $container;

    /**
     * @var CallbackHandler
     */
    private $callbackHandler;

    public function __construct()
    {
        $this->container = Drupal::getContainer();
        $this->callbackHandler = $this->container->get(CallbackHandler::class);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return array
     * @throws \Exception
     */
    public function onNotify(Request $request)
    {
        $response = $this->callbackHandler->execute();

        return [
            '#markup' => $response
        ];
    }
}
