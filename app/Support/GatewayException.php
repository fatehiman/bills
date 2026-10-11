<?php

namespace App\Support;

use RuntimeException;

/** The gateway could not get an answer from any provider (or none is configured). */
class GatewayException extends RuntimeException
{
    /**
     * @param  array  $attempts  List of ['provider' => key, 'error' => text]
     */
    public function __construct(string $message, public readonly int $status = 502, public readonly array $attempts = [])
    {
        parent::__construct($message);
    }
}
