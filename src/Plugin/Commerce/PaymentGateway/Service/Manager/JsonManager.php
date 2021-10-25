<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Manager;

use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Exception\Json\EncodeJsonException;
use Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Exception\Json\DecodeJsonException;

class JsonManager
{
    /**
     * @param $value
     * @param int $options
     * @param int $depth
     * @return string
     * @throws EncodeJsonException
     */
    public function encode($value, $options = 0, $depth = 512)
    {
        $data = json_encode($value, $options, $depth);

        if (!$data) {
            throw new EncodeJsonException('Unable to encode string into JSON');
        }

        return $data;
    }

    /**
     * @param $data
     * @param bool $assoc
     * @param int $depth
     * @param int $options
     * @return array|object
     * @throws DecodeJsonException
     */
    public function decode($data, $assoc = false, $depth = 512, $options = 0)
    {
        $data = json_decode($data, $assoc, $depth, $options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DecodeJsonException('Unable to decode a JSON string');
        }

        return $data;
    }
}
