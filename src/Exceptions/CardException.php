<?php

namespace Bog\Payment\Exceptions;

class CardException extends BogPaymentException
{
    public function __construct(string $message = 'Card operation failed', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
