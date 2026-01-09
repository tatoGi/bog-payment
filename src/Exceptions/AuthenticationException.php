<?php

namespace Bog\Payment\Exceptions;

class AuthenticationException extends BogPaymentException
{
    public function __construct(string $message = 'BOG Payment authentication failed', int $code = 401)
    {
        parent::__construct($message, $code);
    }
}
