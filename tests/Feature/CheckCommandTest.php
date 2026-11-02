<?php

namespace Ahmed29345\Wathq\Tests\Feature;

use Ahmed29345\Wathq\Tests\TestCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class CheckCommandTest extends TestCase
{
    #[Test]
    public function it_prints_an_active_registration_and_succeeds(): void
    {
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);

        $this->artisan('wathq:check', ['cr' => self::CR])
            ->expectsTable(['Field', 'Value'], [
                ['CR number', self::CR],
                ['Found', 'yes'],
                ['Active', 'yes'],
                ['Status ID', 1],
                ['Status', 'Active'],
                ['HTTP status', 200],
            ])
            ->assertExitCode(Command::SUCCESS);
    }

    #[Test]
    public function it_fails_for_an_inactive_registration(): void
    {
        $this->fakeWathq(['crStatus' => ['id' => 2, 'name' => 'Suspended']]);

        $this->artisan('wathq:check', ['cr' => self::CR])
            ->expectsOutputToContain('Suspended')
            ->assertExitCode(Command::FAILURE);
    }

    #[Test]
    public function it_fails_when_the_registration_is_not_found(): void
    {
        $this->fakeWathq([], 404);

        $this->artisan('wathq:check', ['cr' => self::CR])
            ->expectsOutputToContain('404')
            ->assertExitCode(Command::FAILURE);
    }

    #[Test]
    public function it_rejects_a_malformed_number(): void
    {
        Http::fake();

        $this->artisan('wathq:check', ['cr' => '12-34'])
            ->expectsOutputToContain('exactly 10 digits')
            ->assertExitCode(Command::INVALID);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_reports_when_wathq_is_unavailable(): void
    {
        $this->fakeWathq([], 500);

        $this->artisan('wathq:check', ['cr' => self::CR])
            ->expectsOutputToContain('HTTP 500')
            ->doesntExpectOutputToContain(self::API_KEY)
            ->assertExitCode(Command::FAILURE);
    }

    #[Test]
    public function it_can_print_json_including_the_raw_payload(): void
    {
        $this->fakeWathq(['status' => ['id' => 1, 'nameAr' => 'نشط']]);

        $exitCode = Artisan::call('wathq:check', ['cr' => self::CR, '--json' => true]);
        $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertTrue($output['active']);
        $this->assertSame('نشط', $output['status_name']);
        $this->assertSame(['status' => ['id' => 1, 'nameAr' => 'نشط']], $output['raw']);
    }
}
