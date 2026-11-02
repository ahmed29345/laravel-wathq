# Laravel Wathq

[![tests](https://github.com/ahmed29345/laravel-wathq/actions/workflows/tests.yml/badge.svg)](https://github.com/ahmed29345/laravel-wathq/actions/workflows/tests.yml)

A typed Laravel client for checking Saudi commercial registration (CR) numbers against [Wathq](https://developer.wathq.sa). Wathq is the data API platform of the Saudi Ministry of Commerce. Among other things, it lets you confirm that a CR number exists and is active before you onboard a business.

The package gives you:

- a `WathqClient` (and a `Wathq` facade) that returns a `CrVerification` value object
- an `ActiveCommercialRegistration` validation rule, with English and Arabic messages
- a `wathq:check` Artisan command
- a `CrVerified` event, response caching, retries and typed exceptions

> **Unofficial package.** This is a community project. It is not affiliated with, endorsed by or supported by the Ministry of Commerce or Wathq. To use the API you need your own Wathq subscription, and you must follow its terms.

## Requirements

- PHP 8.2+
- Laravel 12 or 13 (Laravel 13 needs PHP 8.3+)
- A Wathq developer portal subscription that includes the commercial registration API

## Installation

```bash
composer require ahmed29345/laravel-wathq
```

The service provider and facade are auto-discovered. To publish the config file:

```bash
php artisan vendor:publish --tag=wathq-config
```

## Configuration

Your Wathq developer portal subscription gives you an API key and a base URL. The sandbox and production base URLs are different, so the package ships with no default. Set them in `.env`:

```dotenv
WATHQ_BASE_URL=        # the base URL shown for your subscription (sandbox or production)
WATHQ_API_KEY=         # your subscription's API key
```

Optional settings and their defaults:

| Variable | Default | Purpose |
| --- | --- | --- |
| `WATHQ_CR_ENDPOINT` | `/v1/commercialregistration/status` | Path appended to the base URL. The CR number is added as the last segment. |
| `WATHQ_TIMEOUT` | `10` | Request timeout, in seconds. |
| `WATHQ_CONNECT_TIMEOUT` | `5` | Connection timeout, in seconds. |
| `WATHQ_RETRIES` | `1` | Extra attempts after a connection failure or a 5xx response. A 4xx is never retried. |
| `WATHQ_RETRY_DELAY_MS` | `250` | Wait between attempts, in milliseconds. |
| `WATHQ_CACHE_TTL` | `3600` | Seconds to cache a found CR. Use `0` to turn caching off. |
| `WATHQ_CACHE_STORE` | app default | Cache store used for lookups. |
| `WATHQ_ALLOW_TEST_CR` | `false` | Must be `true` before the test CR numbers below are honoured. |
| `WATHQ_TEST_CR` | empty | Comma-separated 10-digit numbers that are answered locally as active. |
| `WATHQ_PASS_WHEN_UNAVAILABLE` | `false` | Whether the validation rule lets a value through when Wathq is down. |

Each request is a `GET {base_url}{cr_endpoint}/{cr}` with the headers `Accept: application/json` and `apikey: <key>`.

## Usage

### The client

```php
use Ahmed29345\Wathq\WathqClient;

public function __construct(private WathqClient $wathq) {}

$result = $this->wathq->verifyCr('1010123456');

$result->found;       // bool: false when Wathq answered 404
$result->active;      // bool: true only when found and the status is active
$result->statusId;    // int|string|null, e.g. 1
$result->statusName;  // ?string, e.g. "Active" or "نشط"
$result->httpStatus;  // ?int: null for test CR numbers
$result->raw;         // array: the decoded response body, untouched
$result->simulated;   // bool: true for configured test CR numbers
```

Input is normalised before anything is sent. Surrounding whitespace is trimmed, and Arabic-Indic digits (`١٠١٠١٢٣٤٥٦`) are converted to ASCII. Anything that is not then exactly ten digits throws `InvalidCrNumber`, and no request is made.

A CR that Wathq does not know is a normal result (`found === false`), not an exception. Exceptions are reserved for cases where there is no answer to give:

| Exception | When |
| --- | --- |
| `Ahmed29345\Wathq\Exceptions\InvalidCrNumber` | The input is not a 10-digit CR number. |
| `Ahmed29345\Wathq\Exceptions\WathqUnavailable` | The base URL or key is missing, the request timed out or could not connect, Wathq answered with a status other than 2xx or 404, or a 2xx body was not a JSON object. `$e->httpStatus` holds the status when there was one. |

Both implement `Ahmed29345\Wathq\Exceptions\WathqException`, so you can catch everything from this package in one place.

### The facade

```php
use Ahmed29345\Wathq\Facades\Wathq;

if (Wathq::verifyCr($request->cr_number)->active) {
    // ...
}
```

### The validation rule

```php
use Ahmed29345\Wathq\Rules\ActiveCommercialRegistration;

$request->validate([
    'cr_number' => ['required', new ActiveCommercialRegistration],
]);
```

The rule fails when the value is malformed, when the CR is not found, or when it is not active. Each case has its own message. Messages are available in English and Arabic. Run `php artisan vendor:publish --tag=wathq-lang` to customise them.

By default the rule **fails closed**: if Wathq cannot be reached, the value is rejected with a "try again later" message. Where you would rather accept the value and re-check it later (for example from a queued job), you can opt out per rule or globally:

```php
(new ActiveCommercialRegistration)->passWhenUnavailable();
```

```dotenv
WATHQ_PASS_WHEN_UNAVAILABLE=true
```

If you enable it globally, `->failWhenUnavailable()` brings back the strict behaviour for a single rule.

### The Artisan command

```bash
php artisan wathq:check 1010123456
php artisan wathq:check 1010123456 --json   # full result, including the raw payload
```

The command exits with `0` when the CR is active, `1` when it is not found, inactive or Wathq is unavailable, and `2` when the number is malformed. That makes it usable in scripts.

### Events

`Ahmed29345\Wathq\Events\CrVerified` is dispatched after every completed lookup. That includes not-found results, cache hits (`$event->fromCache`) and test CR numbers. It is not dispatched when a lookup throws. Use it for audit logs or to update your own records:

```php
use Ahmed29345\Wathq\Events\CrVerified;
use Illuminate\Support\Facades\Event;

Event::listen(function (CrVerified $event) {
    logger()->info('CR checked', [
        'cr' => $event->verification->crNumber,
        'active' => $event->verification->active,
    ]);
});
```

### Caching

Lookups where the CR was found are cached for `cache_ttl` seconds. Not-found results and errors are never cached, so a newly issued CR is picked up on the next attempt. Cache keys include a hash of the base URL and endpoint, so switching between sandbox and production never serves the other environment's results.

### Test CR numbers

For demos and staging you can list CR numbers that are answered locally as found and active, without calling Wathq:

```dotenv
WATHQ_ALLOW_TEST_CR=true
WATHQ_TEST_CR=7000000001,7000000002
```

The list is ignored unless `allow_test_cr` is exactly `true`. Nothing turns it on implicitly, and that includes the environment, so a test number can never pass in production by accident. Results for test numbers have `simulated === true` and `httpStatus === null`.

## Response shapes

What the API returns depends on your subscription tier. The status endpoint may return the status object on its own:

```json
{"id": 1, "name": "Active"}
```

Other tiers nest it under `status`, `crStatus` or `registrationStatus`, use `name`, `nameEn` or `nameAr`, and identify it with `id` or `code`:

```json
{"crNumber": "1010123456", "status": {"id": 1, "nameEn": "Active", "nameAr": "نشط"}}
```

The parser checks the wrapper keys first, then falls back to the top level. It treats a registration as active when the status id is `1`, or when any status name is `Active` (case-insensitive), `نشط` or `قائم`. If no status can be found, the result is `found = true, active = false`, which fails safe. The full decoded body is always available in `$result->raw`, so you can read fields this package does not model.

## Security

- The API key is sent only in the `apikey` header, never in the URL.
- Exception messages are written by the package. They never include the response body, and any occurrence of the key in a transport error is redacted.
- `dd()` and `var_dump()` of the client show `[redacted]` instead of the key.
- The package does not log anything itself.

## Testing

### In your application

The client uses Laravel's HTTP client, so `Http::fake()` works as usual:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    '*/commercialregistration/status/*' => Http::response(['id' => 1, 'name' => 'Active']),
]);
```

### This package

```bash
composer install
composer test      # PHPUnit with Orchestra Testbench
composer format    # Laravel Pint
```

CI runs the suite on PHP 8.2, 8.3 and 8.4 against Laravel 12, and on PHP 8.3 and 8.4 against Laravel 13.

## License

MIT. See [LICENSE](LICENSE).
