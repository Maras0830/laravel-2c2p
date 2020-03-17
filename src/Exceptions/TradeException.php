<?php
namespace Maras0830\ToCToP\Exceptions;

use Exception;

/**
 * Class TradeException
 * @package Maras0830\ToCToP\Exceptions
 */
class TradeException extends Exception
{
    /**
     * TradeException constructor.
     * @param string $exception_messages
     */
    public function __construct($exception_messages)
    {
        $this->message = $exception_messages;
    }
}