<?php

declare(strict_types=1);

namespace Twitter\Shared\Infrastructure\Persistence\Doctrine;

use InvalidArgumentException;

final class UuidBinaryConverter
{
    private const int UUID_HEX_LENGTH = 32;
    private const int UUID_BYTES_LENGTH = 16;

    public static function toBytes(string $uuid): string
    {
        $hex = strtolower(str_replace('-', '', $uuid));

        if (!preg_match('/^[0-9a-f]{'.self::UUID_HEX_LENGTH.'}$/', $hex)) {
            throw new InvalidArgumentException('Invalid UUID string: '.$uuid);
        }

        $bytes = hex2bin($hex);
        if (false === $bytes || self::UUID_BYTES_LENGTH !== strlen($bytes)) {
            throw new InvalidArgumentException('Failed to convert UUID to bytes: '.$uuid);
        }

        return $bytes;
    }
}
