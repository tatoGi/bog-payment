<?php

namespace Bog\Payment\Exceptions;

use Exception;

class BogPaymentException extends Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => class_basename($this),
        ], $this->getCode() ?: 500);
    }
}
