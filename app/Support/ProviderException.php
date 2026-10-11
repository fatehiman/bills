<?php

namespace App\Support;

use RuntimeException;

/**
 * Thrown by a provider when an inquiry fails.
 *
 * kind decides what the gateway does:
 *  - INVALID_INPUT : the bill identifier is wrong. Do not try other providers.
 *  - UNAVAILABLE   : provider is down, out of credit, blocked, etc. Try the next provider.
 */
class ProviderException extends RuntimeException
{
    public const INVALID_INPUT = 'invalid_input';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(string $message, public readonly string $kind = self::UNAVAILABLE)
    {
        parent::__construct($message);
    }

    public static function invalidInput(string $message): self
    {
        return new self($message, self::INVALID_INPUT);
    }

    public static function unavailable(string $message): self
    {
        return new self($message, self::UNAVAILABLE);
    }
}
