<?php

namespace App\Exceptions;

class SuspiciousListingRejectedException extends \RuntimeException
{
    public function __construct(
        public readonly string $code,
        string $message,
    ) {
        parent::__construct($message);
    }
}
