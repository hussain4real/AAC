<?php

namespace App\Exceptions;

use Exception;

class OutboundRequestBlocked extends Exception
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Outbound request blocked: {$reason}");
    }
}
