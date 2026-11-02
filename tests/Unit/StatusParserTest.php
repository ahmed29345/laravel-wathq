<?php

namespace Ahmed29345\Wathq\Tests\Unit;

use Ahmed29345\Wathq\Support\StatusParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatusParserTest extends TestCase
{
    /**
     * @return iterable<string, array{array<array-key, mixed>, int|string|null, string|null, bool}>
     */
    public static function payloads(): iterable
    {
        yield 'unwrapped status object' => [['id' => 1, 'name' => 'Active'], 1, 'Active', true];
        yield 'nested under status' => [['status' => ['id' => 1, 'name' => 'Active']], 1, 'Active', true];
        yield 'nested under crStatus' => [['crStatus' => ['id' => 2, 'name' => 'Suspended']], 2, 'Suspended', false];
        yield 'nested under registrationStatus' => [['registrationStatus' => ['code' => '1', 'nameEn' => 'Active']], 1, 'Active', true];
        yield 'english name preferred over arabic' => [['status' => ['id' => 1, 'nameAr' => 'نشط', 'nameEn' => 'Active']], 1, 'Active', true];
        yield 'arabic name only (nashit)' => [['status' => ['nameAr' => 'نشط']], null, 'نشط', true];
        yield 'arabic name only (qaim)' => [['crStatus' => ['name' => 'قائم']], null, 'قائم', true];
        yield 'arabic inactive name' => [['status' => ['id' => 3, 'nameAr' => 'منتهي']], 3, 'منتهي', false];
        yield 'name is case-insensitive' => [['status' => ['name' => ' ACTIVE ']], null, 'ACTIVE', true];
        yield 'numeric string id' => [['id' => '1'], 1, null, true];
        yield 'status as plain string' => [['status' => 'Active'], null, 'Active', true];
        yield 'status as plain id' => [['status' => 4], 4, null, false];
        yield 'string code' => [['status' => ['code' => 'EXPIRED', 'name' => 'Expired']], 'EXPIRED', 'Expired', false];
        yield 'wrapper wins over top-level company name' => [['name' => 'Example Trading Co.', 'status' => ['id' => 2, 'name' => 'Suspended']], 2, 'Suspended', false];
        yield 'no recognisable status' => [['crNumber' => '1010123456'], null, null, false];
        yield 'empty body' => [[], null, null, false];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    #[Test]
    #[DataProvider('payloads')]
    public function it_parses_the_status_tolerantly(array $payload, int|string|null $id, ?string $name, bool $active): void
    {
        $this->assertSame(
            ['id' => $id, 'name' => $name, 'active' => $active],
            StatusParser::parse($payload),
        );
    }
}
