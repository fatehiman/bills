<?php

namespace App\Support;

use RuntimeException;

/** The gateway could not get an answer from any provider (or none is configured). */
class GatewayException extends RuntimeException
{
    /**
     * HTTP status is never 5xx on purpose: the CDN in front of the site replaces 5xx bodies with its own page.
     *
     * @param  array  $attempts  List of ['provider' => key, 'error' => text]
     */
    public function __construct(string $message, public readonly int $status = 424, public readonly array $attempts = [], public readonly string $errorCode = 'providers_failed')
    {
        parent::__construct($message);
    }
}
