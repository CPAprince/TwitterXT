<?php

declare(strict_types=1);

namespace Twitter\Tests\Shared\Infrastructure\Persistence;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Twitter\Shared\Infrastructure\Persistence\Doctrine\UuidBinaryConverter;

final class UuidBinaryConverterTest extends TestCase
{
    public function testToBytesConvertsUuidStringTo16Bytes(): void
    {
        $uuid = '019b2bd9-f57c-7088-824e-b6f96f27a1ba';

        $bytes = UuidBinaryConverter::toBytes($uuid);

        self::assertSame(16, strlen($bytes));

        $expected = hex2bin(str_replace('-', '', strtolower($uuid)));
        self::assertSame($expected, $bytes);
    }

    public function testToBytesThrowsOnInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UuidBinaryConverter::toBytes('not-a-uuid');
    }
}
