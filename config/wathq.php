<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API connection
    |--------------------------------------------------------------------------
    |
    | The base URL and API key come from your subscription on the Wathq
    | developer portal (developer.wathq.sa). Sandbox and production use
    | different base URLs, so both are read from the environment and there
    | is deliberately no hard-coded default.
    |
    */

    'base_url' => env('WATHQ_BASE_URL'),

    'api_key' => env('WATHQ_API_KEY'),

    // Path appended to the base URL; the CR number is added as the last segment.
    'cr_endpoint' => env('WATHQ_CR_ENDPOINT', '/v1/commercialregistration/status'),

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    |
    | Timeouts are in seconds. "retries" is the number of extra attempts made
    | after a connection failure or a 5xx response; client errors such as
    | 404 are never retried.
    |
    */

    'timeout' => (int) env('WATHQ_TIMEOUT', 10),

    'connect_timeout' => (int) env('WATHQ_CONNECT_TIMEOUT', 5),

    'retries' => (int) env('WATHQ_RETRIES', 1),

    'retry_delay_ms' => (int) env('WATHQ_RETRY_DELAY_MS', 250),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Successful lookups (the CR was found) are cached for this many seconds.
    | Set to 0 to disable. Not-found results and errors are never cached.
    | Leave the store null to use the application's default cache store.
    |
    */

    'cache_ttl' => (int) env('WATHQ_CACHE_TTL', 3600),

    'cache_store' => env('WATHQ_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Test CR numbers
    |--------------------------------------------------------------------------
    |
    | CR numbers listed here are answered locally as "found and active"
    | without calling Wathq, which is handy for demos and staging. They are
    | only honoured when "allow_test_cr" is explicitly set to true; the
    | default is false in every environment, including production.
    |
    */

    'allow_test_cr' => env('WATHQ_ALLOW_TEST_CR', false) === true,

    'test_cr' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WATHQ_TEST_CR', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Validation rule
    |--------------------------------------------------------------------------
    |
    | What the ActiveCommercialRegistration rule does when Wathq cannot be
    | reached. The default fails closed: the value is rejected with a
    | "try again later" message. Set to true to let the value through.
    |
    */

    'validation' => [
        'pass_when_unavailable' => (bool) env('WATHQ_PASS_WHEN_UNAVAILABLE', false),
    ],

];
