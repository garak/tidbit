<?php

namespace Garak\Tidbit\Tests;

use Garak\Tidbit\Exception\InvalidTidbitException;
use Garak\Tidbit\Tidbit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TidbitTest extends TestCase
{
    #[Test]
    public function rejectsNegativeInt(): void
    {
        $this->expectException(InvalidTidbitException::class);
        $this->expectExceptionMessage('Tidbit must be a non-negative integer, got -1.');
        Tidbit::fromInt(-1);
    }

    #[Test]
    public function roundTripsThroughInt(): void
    {
        self::assertSame(\PHP_INT_MAX, Tidbit::fromInt(\PHP_INT_MAX)->toInt());
    }

    #[Test]
    public function roundTripsThroughString(): void
    {
        $tidbit = Tidbit::fromInt(123456789012345);

        self::assertSame(11, \strlen($tidbit->toString()));
        self::assertTrue($tidbit->equals(Tidbit::fromString($tidbit->toString())));
        self::assertSame($tidbit->toString(), (string) $tidbit);
    }

    #[Test]
    public function extractsTimestamp(): void
    {
        $tidbit = Tidbit::fromInt((1234 << Tidbit::RANDOM_BITS) | 0x3FFFFF);

        self::assertSame(Tidbit::EPOCH + 1234, $tidbit->getTimestamp());
        self::assertSame('2026-01-01T00:00:01.234+00:00', $tidbit->getDateTime()->format('Y-m-d\TH:i:s.vP'));
    }

    #[Test]
    public function epochIsStartOf2026(): void
    {
        self::assertSame('2026-01-01T00:00:00.000+00:00', Tidbit::fromInt(0)->getDateTime()->format('Y-m-d\TH:i:s.vP'));
    }

    #[Test]
    public function serialisesToJsonString(): void
    {
        self::assertSame('"11111111112"', \json_encode(Tidbit::fromInt(1)));
    }

    #[Test]
    public function compares(): void
    {
        $a = Tidbit::fromInt(1);
        $b = Tidbit::fromInt(2);

        self::assertSame(-1, $a->compare($b));
        self::assertSame(1, $b->compare($a));
        self::assertSame(0, $a->compare(Tidbit::fromInt(1)));
        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function validatesStrings(): void
    {
        self::assertTrue(Tidbit::isValid('NQm6nKp8qFC'));
        self::assertFalse(Tidbit::isValid('NQm6nKp8qFD'));
        self::assertFalse(Tidbit::isValid('not-a-tidbit'));
    }
}
