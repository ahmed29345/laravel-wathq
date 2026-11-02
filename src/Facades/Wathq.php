<?php

namespace Ahmed29345\Wathq\Facades;

use Ahmed29345\Wathq\CrVerification;
use Ahmed29345\Wathq\WathqClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static CrVerification verifyCr(string $crNumber)
 *
 * @see WathqClient
 */
final class Wathq extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WathqClient::class;
    }
}
