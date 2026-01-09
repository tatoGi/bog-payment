<?php

namespace Bog\Payment\Exceptions;

class OrderCreationException extends BogPaymentException
{
    public function __construct(string $message = 'Failed to create BOG payment order', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
