<?php

namespace Drupal\commerce_fondy\Plugin\Commerce\PaymentGateway\Service\Manager;

class SessionManager
{
    const PATTERN_SESSION_NAME_EXPIRATION = '%s_expire';

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name)
    {
        $this->cleanup($name);

        return isset($name, $_SESSION[$name]);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param int|null $expire
     * @return void
     */
    public function set(string $name, $value, $expire = null)
    {
        $_SESSION[$name] = $value;

        if ($expire) {
            $this->set(sprintf(self::PATTERN_SESSION_NAME_EXPIRATION, $name), time() + $expire);
        }
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function get(string $name)
    {
        if ($this->has($name)) {
            return $_SESSION[$name];
        }

        return null;
    }

    /**
     * @param string $name
     * @return void
     */
    public function delete(string $name)
    {
        if ($this->has($name)) {
            unset($_SESSION[$name]);
        }
    }

    /**
     * @param string $name
     * @return void
     */
    private function cleanup(string $name)
    {
        $name = sprintf(self::PATTERN_SESSION_NAME_EXPIRATION, $name);

        if (isset($_SESSION[$name]) && $_SESSION[$name] <= time()) {
            unset($_SESSION[$name]);
            $session = sprintf(self::PATTERN_SESSION_NAME_EXPIRATION, '');
            $name = str_replace($session, '', $name);
            unset($_SESSION[$name]);
        }
    }
}
