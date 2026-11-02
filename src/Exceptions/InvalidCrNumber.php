<?php

namespace Ahmed29345\Wathq\Exceptions;

use InvalidArgumentException;

/**
 * The given value is not a well-formed commercial registration number.
 *
 * Thrown before any request is made, so it never costs an API call.
 */
final class InvalidCrNumber extends InvalidArgumentException implements WathqException
{
    public static function mustBeTenDigits(): self
    {
        return new self('A commercial registration number must be exactly 10 digits.');
    }
}
