<?php

namespace Ahmed29345\Wathq\Support;

use Ahmed29345\Wathq\Exceptions\InvalidCrNumber;

final class CrNumber
{
    private const EASTERN_ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /**
     * Normalise user input to a 10-digit CR number.
     *
     * Surrounding whitespace is trimmed and Arabic-Indic digits (as typed on
     * an Arabic keyboard) are converted to ASCII. Anything else that is not
     * exactly ten digits is rejected.
     *
     * @throws InvalidCrNumber
     */
    public static function normalize(string $value): string
    {
        $ascii = range('0', '9');

        $value = str_replace(self::EASTERN_ARABIC_DIGITS, $ascii, trim($value));
        $value = str_replace(self::PERSIAN_DIGITS, $ascii, $value);

        if (preg_match('/^\d{10}$/D', $value) !== 1) {
            throw InvalidCrNumber::mustBeTenDigits();
        }

        return $value;
    }

    public static function isValid(string $value): bool
    {
        try {
            self::normalize($value);

            return true;
        } catch (InvalidCrNumber) {
            return false;
        }
    }
}
