<?php

namespace Garak\Tidbit\Tests;

use Garak\Tidbit\Base58;
use Garak\Tidbit\Exception\InvalidTidbitException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Base58Test extends TestCase
{
    /**
     * @return iterable<array{int, string}>
     */
    public static function provideKnownValues(): iterable
    {
        yield [0, '11111111111'];
        yield [1, '11111111112'];
        yield [57, '1111111111z'];
        yield [58, '11111111121'];
        yield [\PHP_INT_MAX, 'NQm6nKp8qFC'];
    }

    #[Test]
    #[DataProvider('provideKnownValues')]
    public function encodeAndDecode(int $value, string $encoded): void
    {
        self::assertSame($encoded, Base58::encode($value));
        self::assertSame($value, Base58::decode($encoded));
    }

    #[Test]
    public function stringOrderMatchesNumericOrder(): void
    {
        $values = [];
        for ($i = 0; $i < 1000; ++$i) {
            $values[] = \random_int(0, \PHP_INT_MAX >> \random_int(0, 62));
        }
        $strings = \array_map(Base58::encode(...), $values);
        \sort($values);
        \sort($strings, \SORT_STRING);

        self::assertSame(\array_map(Base58::encode(...), $values), $strings);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function provideInvalidStrings(): iterable
    {
        yield 'too short' => ['1111111111', 'Tidbit "1111111111" must be 11 characters long.'];
        yield 'too long' => ['111111111111', 'Tidbit "111111111111" must be 11 characters long.'];
        yield 'capital O' => ['1111111111O', 'Tidbit "1111111111O" contains invalid character "O".'];
        yield 'capital I' => ['1111111111I', 'Tidbit "1111111111I" contains invalid character "I".'];
        yield 'lower l' => ['1111111111l', 'Tidbit "1111111111l" contains invalid character "l".'];
        yield 'digit 0' => ['11111111110', 'Tidbit "11111111110" contains invalid character "0".'];
        yield 'overflow' => ['zzzzzzzzzzz', 'Tidbit "zzzzzzzzzzz" exceeds the 63-bit range.'];
        yield 'just above max' => ['NQm6nKp8qFD', 'Tidbit "NQm6nKp8qFD" exceeds the 63-bit range.'];
    }

    #[Test]
    #[DataProvider('provideInvalidStrings')]
    public function decodeRejectsInvalidStrings(string $encoded, string $message): void
    {
        $this->expectException(InvalidTidbitException::class);
        $this->expectExceptionMessage($message);
        Base58::decode($encoded);
    }
}
