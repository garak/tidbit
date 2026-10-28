<?php

namespace Garak\Tidbit;

use Garak\Tidbit\Exception\InvalidTidbitException;

/**
 * Fixed-length Base58 codec for non-negative 63-bit integers.
 *
 * The Bitcoin alphabet is in ASCII order, so left-padding with its lowest
 * character keeps string order equal to numeric order.
 *
 * @internal
 */
final class Base58
{
    public const string ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    public const int LENGTH = 11;

    public static function encode(int $value): string
    {
        $result = '';
        do {
            $result = self::ALPHABET[$value % 58].$result;
            $value = \intdiv($value, 58);
        } while ($value > 0);

        return \str_pad($result, self::LENGTH, self::ALPHABET[0], \STR_PAD_LEFT);
    }

    public static function decode(string $encoded): int
    {
        if (self::LENGTH !== \strlen($encoded)) {
            throw InvalidTidbitException::length($encoded, self::LENGTH);
        }

        $value = 0;
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $digit = \strpos(self::ALPHABET, $encoded[$i]);
            if (false === $digit) {
                throw InvalidTidbitException::character($encoded, $encoded[$i]);
            }
            // 58^11 > 2^63, so the top of the string range does not fit in an int
            if ($value > \intdiv(\PHP_INT_MAX - $digit, 58)) {
                throw InvalidTidbitException::overflow($encoded);
            }
            $value = $value * 58 + $digit;
        }

        return $value;
    }
}
