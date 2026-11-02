<?php

namespace Ahmed29345\Wathq;

use Ahmed29345\Wathq\Events\CrVerified;
use Ahmed29345\Wathq\Exceptions\InvalidCrNumber;
use Ahmed29345\Wathq\Exceptions\WathqUnavailable;
use Ahmed29345\Wathq\Support\CrNumber;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Event;
use JsonException;
use SensitiveParameter;
use Throwable;

class WathqClient
{
    /**
     * @param  array<string, mixed>  $config  The "wathq" configuration array.
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheFactory $cache,
        #[SensitiveParameter] private readonly array $config,
    ) {}

    /**
     * Look up a commercial registration number.
     *
     * A CR that Wathq does not know is a normal result (found = false), not an
     * exception. Exceptions are reserved for bad input and for situations in
     * which Wathq could not give an answer at all.
     *
     * @throws InvalidCrNumber
     * @throws WathqUnavailable
     */
    public function verifyCr(string $crNumber): CrVerification
    {
        $crNumber = CrNumber::normalize($crNumber);

        if ($this->isTestCr($crNumber)) {
            return $this->dispatched(CrVerification::simulatedActive($crNumber), fromCache: false);
        }

        $cacheKey = $this->cacheKey($crNumber);

        if ($cacheKey !== null && is_array($cached = $this->cacheStore()->get($cacheKey))) {
            return $this->dispatched(CrVerification::fromArray($cached), fromCache: true);
        }

        $verification = $this->fetch($crNumber);

        if ($cacheKey !== null && $verification->found) {
            $this->cacheStore()->put($cacheKey, $verification->toArray(), (int) $this->config['cache_ttl']);
        }

        return $this->dispatched($verification, fromCache: false);
    }

    /**
     * @throws WathqUnavailable
     */
    private function fetch(string $crNumber): CrVerification
    {
        try {
            $response = $this->request()->get($this->endpoint().'/'.$crNumber);
        } catch (ConnectionException|TransferException $e) {
            throw WathqUnavailable::connectionFailed($this->redact($e->getMessage()));
        }

        if ($response->notFound()) {
            return CrVerification::notFound($crNumber, $this->decode($response) ?? [], $response->status());
        }

        if (! $response->successful()) {
            throw WathqUnavailable::unexpectedStatus($response->status());
        }

        $payload = $this->decode($response)
            ?? throw WathqUnavailable::invalidResponse($response->status());

        return CrVerification::fromPayload($crNumber, $payload, $response->status());
    }

    private function request(): PendingRequest
    {
        $baseUrl = $this->requireConfig('base_url');
        $apiKey = $this->requireConfig('api_key');

        $request = $this->http
            ->baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->withHeaders(['apikey' => $apiKey])
            ->timeout((int) ($this->config['timeout'] ?? 10))
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 5));

        $retries = max(0, (int) ($this->config['retries'] ?? 0));

        if ($retries > 0) {
            $request->retry(
                times: $retries + 1,
                sleepMilliseconds: max(0, (int) ($this->config['retry_delay_ms'] ?? 0)),
                when: fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
        }

        return $request;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function decode(Response $response): ?array
    {
        try {
            $decoded = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function isTestCr(string $crNumber): bool
    {
        if (($this->config['allow_test_cr'] ?? false) !== true) {
            return false;
        }

        return in_array($crNumber, array_map('strval', (array) ($this->config['test_cr'] ?? [])), true);
    }

    private function cacheKey(string $crNumber): ?string
    {
        if ((int) ($this->config['cache_ttl'] ?? 0) <= 0) {
            return null;
        }

        // Scope entries to the upstream so switching sandbox/production or
        // subscription tier never serves a result from the other one.
        $scope = substr(hash('sha256', ($this->config['base_url'] ?? '').'|'.$this->endpoint()), 0, 16);

        return "wathq:cr:{$scope}:{$crNumber}";
    }

    private function cacheStore(): CacheRepository
    {
        return $this->cache->store($this->config['cache_store'] ?? null);
    }

    private function endpoint(): string
    {
        return '/'.trim((string) ($this->config['cr_endpoint'] ?? ''), '/');
    }

    private function requireConfig(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));

        if ($value === '') {
            throw WathqUnavailable::missingConfiguration($key);
        }

        return $value;
    }

    private function redact(string $message): string
    {
        $apiKey = trim((string) ($this->config['api_key'] ?? ''));

        return $apiKey === '' ? $message : str_replace($apiKey, '[redacted]', $message);
    }

    private function dispatched(CrVerification $verification, bool $fromCache): CrVerification
    {
        Event::dispatch(new CrVerified($verification, $fromCache));

        return $verification;
    }

    /**
     * Keep the API key out of dd(), dump() and var_dump() output.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['config' => array_merge($this->config, ['api_key' => '[redacted]'])];
    }
}
