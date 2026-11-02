<?php

namespace Ahmed29345\Wathq\Tests\Feature;

use Ahmed29345\Wathq\CrVerification;
use Ahmed29345\Wathq\Events\CrVerified;
use Ahmed29345\Wathq\Exceptions\InvalidCrNumber;
use Ahmed29345\Wathq\Exceptions\WathqException;
use Ahmed29345\Wathq\Exceptions\WathqUnavailable;
use Ahmed29345\Wathq\Facades\Wathq;
use Ahmed29345\Wathq\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class WathqClientTest extends TestCase
{
    #[Test]
    public function it_reports_an_active_registration(): void
    {
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);

        $result = $this->client()->verifyCr(self::CR);

        $this->assertInstanceOf(CrVerification::class, $result);
        $this->assertSame(self::CR, $result->crNumber);
        $this->assertTrue($result->found);
        $this->assertTrue($result->active);
        $this->assertSame(1, $result->statusId);
        $this->assertSame('Active', $result->statusName);
        $this->assertSame(200, $result->httpStatus);
        $this->assertSame(['id' => 1, 'name' => 'Active'], $result->raw);
        $this->assertFalse($result->simulated);
    }

    #[Test]
    public function it_reports_an_inactive_registration(): void
    {
        $this->fakeWathq(['crStatus' => ['id' => 2, 'nameEn' => 'Suspended', 'nameAr' => 'موقوف']]);

        $result = $this->client()->verifyCr(self::CR);

        $this->assertTrue($result->found);
        $this->assertFalse($result->active);
        $this->assertSame(2, $result->statusId);
        $this->assertSame('Suspended', $result->statusName);
    }

    #[Test]
    public function it_handles_the_nested_shape_used_by_other_tiers(): void
    {
        $payload = [
            'crNumber' => self::CR,
            'name' => 'Example Trading Co.',
            'status' => ['id' => 1, 'nameAr' => 'نشط', 'nameEn' => 'Active'],
        ];
        $this->fakeWathq($payload);

        $result = $this->client()->verifyCr(self::CR);

        $this->assertTrue($result->active);
        $this->assertSame('Active', $result->statusName);
        $this->assertSame($payload, $result->raw, 'The raw payload is kept intact.');
    }

    #[Test]
    public function it_recognises_arabic_status_names(): void
    {
        $this->fakeWathq(['registrationStatus' => ['nameAr' => 'قائم']]);

        $result = $this->client()->verifyCr(self::CR);

        $this->assertTrue($result->active);
        $this->assertSame('قائم', $result->statusName);
    }

    #[Test]
    public function a_404_means_not_found_rather_than_an_error(): void
    {
        $this->fakeWathq(['code' => 'NotFound', 'message' => 'CR not found'], 404);

        $result = $this->client()->verifyCr(self::CR);

        $this->assertFalse($result->found);
        $this->assertFalse($result->active);
        $this->assertNull($result->statusId);
        $this->assertSame(404, $result->httpStatus);
    }

    #[Test]
    public function a_server_error_throws_wathq_unavailable(): void
    {
        $this->fakeWathq(['message' => 'Internal error'], 500);

        try {
            $this->client()->verifyCr(self::CR);
            $this->fail('Expected WathqUnavailable.');
        } catch (WathqUnavailable $e) {
            $this->assertSame(500, $e->httpStatus);
            $this->assertInstanceOf(WathqException::class, $e);
        }
    }

    #[Test]
    public function other_client_errors_are_service_errors_too(): void
    {
        $this->fakeWathq(['message' => 'Invalid API key'], 401);

        $this->expectException(WathqUnavailable::class);
        $this->expectExceptionMessage('HTTP 401');

        $this->client()->verifyCr(self::CR);
    }

    #[Test]
    public function a_timeout_throws_wathq_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds'));

        $this->expectException(WathqUnavailable::class);
        $this->expectExceptionMessage('Operation timed out');

        $this->client()->verifyCr(self::CR);
    }

    #[Test]
    public function a_successful_body_that_is_not_json_throws(): void
    {
        $this->fakeWathq('<html>Gateway</html>');

        $this->expectException(WathqUnavailable::class);
        $this->expectExceptionMessage('not a JSON object');

        $this->client()->verifyCr(self::CR);
    }

    #[Test]
    public function it_retries_server_errors_and_connection_failures(): void
    {
        Http::fakeSequence(self::ENDPOINT.'/*')
            ->pushStatus(503)
            ->push(['id' => 1, 'name' => 'Active']);

        $result = $this->client(['retries' => 2])->verifyCr(self::CR);

        $this->assertTrue($result->active);
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_does_not_retry_a_404(): void
    {
        $this->fakeWathq([], 404);

        $this->client(['retries' => 3])->verifyCr(self::CR);

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_sends_the_expected_request(): void
    {
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);

        $this->client(['base_url' => self::BASE_URL.'/'])->verifyCr(self::CR);

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::ENDPOINT.'/'.self::CR
            && $request->header('apikey') === [self::API_KEY]
            && $request->header('Accept') === ['application/json']);
    }

    #[Test]
    public function it_uses_a_custom_endpoint(): void
    {
        Http::fake([self::BASE_URL.'/v2/cr/info/*' => Http::response(['status' => ['id' => 1]])]);

        $result = $this->client(['cr_endpoint' => 'v2/cr/info/'])->verifyCr(self::CR);

        $this->assertTrue($result->active);
        Http::assertSent(fn (Request $request) => $request->url() === self::BASE_URL.'/v2/cr/info/'.self::CR);
    }

    #[Test]
    public function it_validates_the_format_before_calling_wathq(): void
    {
        Http::fake();

        try {
            $this->client()->verifyCr('12345');
            $this->fail('Expected InvalidCrNumber.');
        } catch (InvalidCrNumber $e) {
            $this->assertInstanceOf(WathqException::class, $e);
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function it_normalises_arabic_digits_before_the_request(): void
    {
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);

        $result = $this->client()->verifyCr('١٠١٠١٢٣٤٥٦');

        $this->assertSame(self::CR, $result->crNumber);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/'.self::CR));
    }

    #[Test]
    public function missing_configuration_throws_without_a_request(): void
    {
        Http::fake();

        foreach (['base_url', 'api_key'] as $key) {
            try {
                $this->client([$key => null])->verifyCr(self::CR);
                $this->fail("Expected WathqUnavailable for missing {$key}.");
            } catch (WathqUnavailable $e) {
                $this->assertStringContainsString("wathq.{$key}", $e->getMessage());
            }

            config()->set('wathq.base_url', self::BASE_URL);
            config()->set('wathq.api_key', self::API_KEY);
        }

        Http::assertNothingSent();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function failureScenarios(): iterable
    {
        yield 'server error echoing the key' => ['server-error'];
        yield 'unauthorised echoing the key' => ['unauthorised'];
        yield 'connection error mentioning the key' => ['connection-error'];
        yield 'invalid json' => ['invalid-json'];
    }

    #[Test]
    #[DataProvider('failureScenarios')]
    public function the_api_key_never_appears_in_exception_messages(string $scenario): void
    {
        match ($scenario) {
            'server-error' => $this->fakeWathq(['echo' => self::API_KEY], 500),
            'unauthorised' => $this->fakeWathq('Invalid apikey '.self::API_KEY, 403),
            'connection-error' => Http::fake(fn () => throw new ConnectionException('Failed with header apikey: '.self::API_KEY)),
            'invalid-json' => $this->fakeWathq('apikey='.self::API_KEY),
        };

        try {
            $this->client()->verifyCr(self::CR);
            $this->fail('Expected WathqUnavailable.');
        } catch (WathqUnavailable $e) {
            $this->assertStringNotContainsString(self::API_KEY, $e->getMessage());
            $this->assertStringNotContainsString(self::API_KEY, (string) $e);
        }
    }

    #[Test]
    public function the_api_key_is_hidden_from_debug_dumps(): void
    {
        $dump = print_r($this->client(), true);

        $this->assertStringNotContainsString(self::API_KEY, $dump);
        $this->assertStringContainsString('[redacted]', $dump);
    }

    #[Test]
    public function successful_lookups_are_cached(): void
    {
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);
        $client = $this->client(['cache_ttl' => 600]);

        $first = $client->verifyCr(self::CR);
        $second = $client->verifyCr(self::CR);

        Http::assertSentCount(1);
        $this->assertEquals($first, $second);
    }

    #[Test]
    public function not_found_results_are_not_cached(): void
    {
        $this->fakeWathq([], 404);
        $client = $this->client(['cache_ttl' => 600]);

        $client->verifyCr(self::CR);
        $client->verifyCr(self::CR);

        Http::assertSentCount(2);
    }

    #[Test]
    public function caching_can_be_disabled(): void
    {
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);
        $client = $this->client(['cache_ttl' => 0]);

        $client->verifyCr(self::CR);
        $client->verifyCr(self::CR);

        Http::assertSentCount(2);
    }

    #[Test]
    public function the_cache_is_scoped_to_the_upstream(): void
    {
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);
        Http::fake(['https://other.test/*' => Http::response(['id' => 2, 'name' => 'Suspended'])]);

        $this->assertTrue($this->client(['cache_ttl' => 600])->verifyCr(self::CR)->active);
        $this->assertFalse($this->client(['base_url' => 'https://other.test'])->verifyCr(self::CR)->active);
    }

    #[Test]
    public function test_numbers_are_ignored_unless_explicitly_allowed(): void
    {
        $this->fakeWathq([], 404);

        $this->assertFalse($this->client(['test_cr' => [self::CR]])->verifyCr(self::CR)->found);
        $this->assertFalse($this->client(['allow_test_cr' => 'true'])->verifyCr(self::CR)->found);

        Http::assertSentCount(2);
    }

    #[Test]
    public function allowed_test_numbers_are_answered_locally(): void
    {
        Http::fake();

        $result = $this->client(['allow_test_cr' => true, 'test_cr' => [self::CR]])->verifyCr(self::CR);

        $this->assertTrue($result->active);
        $this->assertTrue($result->simulated);
        $this->assertNull($result->httpStatus);
        Http::assertNothingSent();
    }

    #[Test]
    public function an_event_is_dispatched_after_each_lookup(): void
    {
        Event::fake([CrVerified::class]);
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);
        $client = $this->client(['cache_ttl' => 600]);

        $client->verifyCr(self::CR);
        $client->verifyCr(self::CR);

        Event::assertDispatchedTimes(CrVerified::class, 2);
        Event::assertDispatched(CrVerified::class, fn (CrVerified $e) => ! $e->fromCache && $e->verification->active);
        Event::assertDispatched(CrVerified::class, fn (CrVerified $e) => $e->fromCache);
    }

    #[Test]
    public function no_event_is_dispatched_when_the_lookup_fails(): void
    {
        Event::fake([CrVerified::class]);
        $this->fakeWathq([], 502);

        try {
            $this->client()->verifyCr(self::CR);
        } catch (WathqUnavailable) {
        }

        Event::assertNotDispatched(CrVerified::class);
    }

    #[Test]
    public function the_facade_proxies_to_the_client(): void
    {
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);

        $this->assertTrue(Wathq::verifyCr(self::CR)->active);
    }
}
