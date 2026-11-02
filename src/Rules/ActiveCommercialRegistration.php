<?php

namespace Ahmed29345\Wathq\Rules;

use Ahmed29345\Wathq\Exceptions\WathqUnavailable;
use Ahmed29345\Wathq\Support\CrNumber;
use Ahmed29345\Wathq\WathqClient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

/**
 * Passes only for a CR number that Wathq reports as found and active.
 *
 * When Wathq cannot be reached the rule fails closed by default. Call
 * passWhenUnavailable() (or set wathq.validation.pass_when_unavailable)
 * to accept the value instead, for example on a form where the CR is
 * re-checked later by a queued job.
 */
final class ActiveCommercialRegistration implements ValidationRule
{
    private ?bool $passWhenUnavailable = null;

    public function passWhenUnavailable(bool $pass = true): self
    {
        $this->passWhenUnavailable = $pass;

        return $this;
    }

    public function failWhenUnavailable(): self
    {
        return $this->passWhenUnavailable(false);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_string($value) && ! is_int($value)) || ! CrNumber::isValid((string) $value)) {
            $fail('wathq::validation.invalid_format')->translate();

            return;
        }

        try {
            $verification = App::make(WathqClient::class)->verifyCr((string) $value);
        } catch (WathqUnavailable) {
            if (! $this->shouldPassWhenUnavailable()) {
                $fail('wathq::validation.unavailable')->translate();
            }

            return;
        }

        if (! $verification->found) {
            $fail('wathq::validation.not_found')->translate();
        } elseif (! $verification->active) {
            $fail('wathq::validation.inactive')->translate();
        }
    }

    private function shouldPassWhenUnavailable(): bool
    {
        return $this->passWhenUnavailable
            ?? (bool) Config::get('wathq.validation.pass_when_unavailable', false);
    }
}
