<?php

namespace Ahmed29345\Wathq\Tests\Unit;

use Ahmed29345\Wathq\Exceptions\InvalidCrNumber;
use Ahmed29345\Wathq\Support\CrNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CrNumberTest extends TestCase
{
    #[Test]
    public function it_accepts_ten_ascii_digits(): void
    {
        $this->assertSame('1010123456', CrNumber::normalize('1010123456'));
    }

    #[Test]
    public function it_trims_whitespace_and_converts_arabic_indic_digits(): void
    {
        $this->assertSame('1010123456', CrNumber::normalize('  ١٠١٠١٢٣٤٥٦ '));
        $this->assertSame('1010123456', CrNumber::normalize('۱۰۱۰۱۲۳۴۵۶'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['101012345'];
        yield 'too long' => ['10101234567'];
        yield 'letters' => ['10101234AB'];
        yield 'hyphenated' => ['1010-123456'];
        yield 'inner space' => ['10101 23456'];
        yield 'trailing newline' => ["1010123456\n1"];
        yield 'path traversal' => ['../../etc/p'];
    }

    #[Test]
    #[DataProvider('invalidNumbers')]
    public function it_rejects_malformed_numbers(string $value): void
    {
        $this->assertFalse(CrNumber::isValid($value));

        $this->expectException(InvalidCrNumber::class);

        CrNumber::normalize($value);
    }
}
