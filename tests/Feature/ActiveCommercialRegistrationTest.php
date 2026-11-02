<?php

namespace Ahmed29345\Wathq\Tests\Feature;

use Ahmed29345\Wathq\Rules\ActiveCommercialRegistration;
use Ahmed29345\Wathq\Tests\TestCase;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

final class ActiveCommercialRegistrationTest extends TestCase
{
    private function validate(mixed $value, ?ActiveCommercialRegistration $rule = null): ValidatorContract
    {
        return Validator::make(
            ['cr_number' => $value],
            ['cr_number' => ['required', $rule ?? new ActiveCommercialRegistration]],
        );
    }

    #[Test]
    public function it_passes_for_an_active_registration(): void
    {
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);

        $this->assertTrue($this->validate(self::CR)->passes());
    }

    #[Test]
    public function it_accepts_an_integer_value(): void
    {
        $this->fakeWathq(['id' => 1, 'name' => 'Active']);

        $this->assertTrue($this->validate((int) self::CR)->passes());
    }

    #[Test]
    public function it_fails_for_an_inactive_registration(): void
    {
        $this->fakeWathq(['status' => ['id' => 3, 'name' => 'Expired']]);

        $validator = $this->validate(self::CR);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'The cr number is not an active commercial registration.',
            $validator->errors()->first('cr_number'),
        );
    }

    #[Test]
    public function it_fails_when_the_registration_is_not_found(): void
    {
        $this->fakeWathq([], 404);

        $validator = $this->validate(self::CR);

        $this->assertSame(
            'The cr number was not found in the commercial registry.',
            $validator->errors()->first('cr_number'),
        );
    }

    #[Test]
    public function it_rejects_a_malformed_value_without_calling_wathq(): void
    {
        Http::fake();

        foreach (['123', 'abcdefghij', ['1010123456'], 1010123456.0] as $value) {
            $validator = $this->validate($value);

            $this->assertSame(
                'The cr number must be a 10-digit commercial registration number.',
                $validator->errors()->first('cr_number'),
            );
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function it_fails_closed_when_wathq_is_unavailable(): void
    {
        $this->fakeWathq([], 503);

        $validator = $this->validate(self::CR);

        $this->assertSame(
            'The cr number could not be verified right now. Please try again later.',
            $validator->errors()->first('cr_number'),
        );
    }

    #[Test]
    public function it_can_pass_when_wathq_is_unavailable(): void
    {
        $this->fakeWathq([], 503);

        $this->assertTrue($this->validate(self::CR, (new ActiveCommercialRegistration)->passWhenUnavailable())->passes());
    }

    #[Test]
    public function the_unavailable_behaviour_can_be_set_in_config(): void
    {
        $this->fakeWathq([], 503);
        config()->set('wathq.validation.pass_when_unavailable', true);

        $this->assertTrue($this->validate(self::CR)->passes());
        $this->assertTrue($this->validate(self::CR, (new ActiveCommercialRegistration)->failWhenUnavailable())->fails());
    }

    #[Test]
    public function it_treats_missing_configuration_as_unavailable(): void
    {
        Http::fake();
        $this->client(['api_key' => null]);

        $this->assertTrue($this->validate(self::CR)->fails());
        Http::assertNothingSent();
    }

    #[Test]
    public function messages_are_translated(): void
    {
        $this->fakeWathq(['status' => ['id' => 2, 'nameAr' => 'موقوف']]);
        App::setLocale('ar');

        $message = $this->validate(self::CR)->errors()->first('cr_number');

        $this->assertStringContainsString('ليس سجلًا تجاريًا نشطًا', $message);
    }
}
