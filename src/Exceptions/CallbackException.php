<?php

namespace Bog\Payment\Exceptions;

class CallbackException extends BogPaymentException
{
    public function __construct(string $message = 'BOG callback processing failed', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
