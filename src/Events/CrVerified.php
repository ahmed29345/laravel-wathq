<?php

namespace Ahmed29345\Wathq\Events;

use Ahmed29345\Wathq\CrVerification;

/**
 * Dispatched after every completed lookup, including not-found results,
 * cache hits and test CR numbers. Not dispatched when a lookup throws.
 */
final class CrVerified
{
    public function __construct(
        public readonly CrVerification $verification,
        public readonly bool $fromCache = false,
    ) {}
}
