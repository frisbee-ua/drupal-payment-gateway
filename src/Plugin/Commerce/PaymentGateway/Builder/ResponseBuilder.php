<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Builder;

use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Manager\JsonManager;

final class ResponseBuilder
{
    /**
     * @var \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Manager\JsonManager
     */
    private $jsonManager;

    public function __construct()
    {
        $this->jsonManager = new JsonManager();
    }

    /**
     * @param $url
     * @param null $message
     * @return string
     * @throws \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Exception\Json\EncodeJsonException
     */
    public function url($url, $message = null)
    {
        $response = compact('url');

        if ($message) {
            $response['message'] = $message;
        }

        return $this->json($response);
    }

    /**
     * @param $token
     * @param $options
     * @param null $message
     * @return string
     * @throws \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Exception\Json\EncodeJsonException
     */
    public function token($token, $options, $message = null)
    {
        $response = compact('token', 'options');

        if ($message) {
            $response['message'] = $message;
        }

        return $this->json($response);
    }

    /**
     * @param $response
     * @return string
     * @throws \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Exception\Json\EncodeJsonException
     */
    public function json($response)
    {
        return $this->jsonManager->encode($response);
    }

    /**
     * @param $message
     * @return string
     * @throws \Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Exception\Json\EncodeJsonException
     */
    public function error($message)
    {
        return $this->json([
            'error' => true,
            'message' => $message
        ]);
    }
}
