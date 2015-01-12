<?php namespace Waynestate\Api;

/**
 * Class ConnectorException
 * @package Waynestate\Api
 */
class ConnectorException extends \Exception
{
    /**
     * @var string
     */
    var $details;
    /**
     * @var string
     */
    var $method;

    /**
     * @param $message
     * @param $method
     * @param int $code
     * @param string $details
     */
    public function __construct($message, $method, $code = 0, $details = '')
    {
        $this->details = $details;
        $this->method = $method;
        parent::__construct($message, $code);
    }

    /**
     * @return string
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "{$this->method} exception [{$this->code}]: {$this->getMessage()} ({$this->details})\n";
    }
}
