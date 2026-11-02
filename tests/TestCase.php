<?php

namespace Ahmed29345\Wathq\Tests;

use Ahmed29345\Wathq\Facades\Wathq;
use Ahmed29345\Wathq\WathqClient;
use Ahmed29345\Wathq\WathqServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const BASE_URL = 'https://wathq.test';

    protected const ENDPOINT = self::BASE_URL.'/v1/commercialregistration/status';

    /** A dummy key; tests assert it never leaks into messages. */
    protected const API_KEY = 'dummy-key-0123456789abcdef';

    protected const CR = '1010123456';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [WathqServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Wathq' => Wathq::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('wathq.base_url', self::BASE_URL);
        $app['config']->set('wathq.api_key', self::API_KEY);
        $app['config']->set('wathq.retries', 0);
        $app['config']->set('wathq.retry_delay_ms', 0);
    }

    /**
     * Resolve a fresh client after applying config overrides.
     *
     * @param  array<string, mixed>  $config  Keys relative to "wathq."
     */
    protected function client(array $config = []): WathqClient
    {
        foreach ($config as $key => $value) {
            config()->set("wathq.{$key}", $value);
        }

        $this->app->forgetInstance(WathqClient::class);

        return $this->app->make(WathqClient::class);
    }

    /**
     * @param  array<array-key, mixed>|string  $body
     */
    protected function fakeWathq(array|string $body, int $status = 200): void
    {
        Http::fake([self::ENDPOINT.'/*' => Http::response($body, $status)]);
    }
}
