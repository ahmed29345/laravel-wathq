<?php

namespace Ahmed29345\Wathq\Exceptions;

use RuntimeException;

/**
 * Wathq could not give a usable answer: the package is not configured,
 * the service timed out, or it replied with an unexpected status.
 *
 * Messages are built by this package and never include the API key or the
 * upstream response body.
 */
final class WathqUnavailable extends RuntimeException implements WathqException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }

    public static function missingConfiguration(string $key): self
    {
        return new self("Wathq is not configured: [wathq.{$key}] is empty.");
    }

    public static function connectionFailed(string $reason): self
    {
        return new self("Wathq could not be reached: {$reason}");
    }

    public static function unexpectedStatus(int $status): self
    {
        return new self("Wathq responded with HTTP {$status}.", $status);
    }

    public static function invalidResponse(int $status): self
    {
        return new self("Wathq responded with HTTP {$status} but the body was not a JSON object.", $status);
    }
}
