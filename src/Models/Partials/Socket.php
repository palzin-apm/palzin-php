<?php


namespace Palzin\Models\Partials;


use Palzin\Models\Arrayable;

class Socket extends Arrayable
{
    /**
     * Socket constructor.
     */
    public function __construct()
    {
        $this->remote_address = $_SERVER['REMOTE_ADDR'] ?? '';

        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER) === true) {
            $this->remote_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        $this->encrypted = isset($_SERVER['HTTPS']);
    }
}
