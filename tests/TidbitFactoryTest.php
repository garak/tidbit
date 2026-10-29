<?php

namespace Garak\Tidbit\Tests;

use Garak\Tidbit\Tidbit;
use Garak\Tidbit\TidbitFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class TidbitFactoryTest extends TestCase
{
    private const int NOW = Tidbit::EPOCH + 1_000_000;

    #[Test]
    public function embedsClockTime(): void
    {
        $factory = new TidbitFactory(clock: new FixedClock(self::NOW));

        self::assertSame(self::NOW, $factory->create()->getTimestamp());
    }

    #[Test]
    public function embedsNode(): void
    {
        $factory = new TidbitFactory(node: 42, clock: new FixedClock(self::NOW));

        self::assertSame(42, ($factory->create()->toInt() >> 12) & 0x3FF);
        self::assertSame(42, $factory->getNode());
    }

    #[Test]
    public function increasesWithinSameMillisecondPastCounterOverflow(): void
    {
        $factory = new TidbitFactory(clock: new FixedClock(self::NOW), randomizer: new Randomizer(new Mt19937(1)));

        $previous = $factory->create();
        // 12 counter bits: 10k IDs in one ms forces several overflows
        for ($i = 0; $i < 10_000; ++$i) {
            $current = $factory->create();
            self::assertSame(1, $current->compare($previous));
            self::assertGreaterThan(0, \strcmp($current->toString(), $previous->toString()));
            $previous = $current;
        }
        self::assertGreaterThan(self::NOW, $previous->getTimestamp());
    }

    #[Test]
    public function keepsIncreasingWhenClockGoesBackwards(): void
    {
        $clock = new FixedClock(self::NOW);
        $factory = new TidbitFactory(clock: $clock);
        $before = $factory->create();

        $clock->milliseconds -= 5_000;

        self::assertSame(1, $factory->create()->compare($before));
    }

    #[Test]
    public function usesAllRandomBitsForCounterWithoutNode(): void
    {
        $factory = new TidbitFactory(nodeBits: 0, clock: new FixedClock(self::NOW));

        self::assertSame(0, $factory->getNode());
        self::assertSame(self::NOW, $factory->create()->getTimestamp());
    }

    #[Test]
    public function distinctNodesNeverCollide(): void
    {
        $clock = new FixedClock(self::NOW);
        $a = new TidbitFactory(node: 1, clock: $clock);
        $b = new TidbitFactory(node: 2, clock: $clock);

        $ids = [];
        for ($i = 0; $i < 5_000; ++$i) {
            $ids[] = $a->create()->toInt();
            $ids[] = $b->create()->toInt();
        }

        self::assertCount(10_000, \array_unique($ids));
    }

    #[Test]
    public function rejectsNodeOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TidbitFactory(node: 1024);
    }

    #[Test]
    public function rejectsNodeBitsOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TidbitFactory(nodeBits: 21);
    }

    #[Test]
    public function rejectsClockBeforeEpoch(): void
    {
        $this->expectException(\OutOfRangeException::class);
        new TidbitFactory(clock: new FixedClock(Tidbit::EPOCH - 1))->create();
    }

    #[Test]
    public function rejectsClockAfterRange(): void
    {
        $factory = new TidbitFactory(clock: new FixedClock(Tidbit::EPOCH + (1 << Tidbit::TIME_BITS) - 1));
        self::assertGreaterThan(0, $factory->create()->toInt());

        $this->expectException(\OverflowException::class);
        new TidbitFactory(clock: new FixedClock(Tidbit::EPOCH + (1 << Tidbit::TIME_BITS)))->create();
    }

    #[Test]
    public function createAtEmbedsGivenTime(): void
    {
        $factory = new TidbitFactory(node: 42, clock: new FixedClock(self::NOW));
        $time = new \DateTimeImmutable('2004-03-15T09:30:00.123+00:00');

        $tidbit = $factory->createAt($time);

        self::assertSame($time->format('Uv'), (string) $tidbit->getTimestamp());
        self::assertEquals($time, $tidbit->getDateTime());
        self::assertSame(42, ($tidbit->toInt() >> 12) & 0x3FF);
    }

    #[Test]
    public function createAtConvertsTimezone(): void
    {
        $factory = new TidbitFactory();
        $time = new \DateTime('2004-03-15T10:30:00.123+01:00');

        self::assertSame('2004-03-15T09:30:00.123+00:00', $factory->createAt($time)->getDateTime()->format('Y-m-d\TH:i:s.vP'));
    }

    #[Test]
    public function createAtAcceptsRangeEdges(): void
    {
        $factory = new TidbitFactory();
        $first = $factory->createAt(new FixedClock(Tidbit::EPOCH)->now());
        $last = $factory->createAt(new FixedClock(Tidbit::EPOCH + (1 << Tidbit::TIME_BITS) - 1)->now());

        self::assertSame('2001-01-01T00:00:00.000+00:00', $first->getDateTime()->format('Y-m-d\TH:i:s.vP'));
        self::assertSame(Tidbit::EPOCH, $first->getTimestamp());
        self::assertSame(Tidbit::EPOCH + (1 << Tidbit::TIME_BITS) - 1, $last->getTimestamp());
        self::assertSame(11, \strlen($last->toString()));
    }

    #[Test]
    public function createAtRejectsTimeBeforeEpoch(): void
    {
        $this->expectException(\OutOfRangeException::class);
        new TidbitFactory()->createAt(new FixedClock(Tidbit::EPOCH - 1)->now());
    }

    #[Test]
    public function createAtRejectsTimeAfterRange(): void
    {
        $this->expectException(\OutOfRangeException::class);
        new TidbitFactory()->createAt(new FixedClock(Tidbit::EPOCH + (1 << Tidbit::TIME_BITS))->now());
    }

    #[Test]
    public function createAtUsesFullCounterRange(): void
    {
        $factory = new TidbitFactory(nodeBits: 0, randomizer: new Randomizer(new Mt19937(1)));
        $time = new \DateTimeImmutable('2004-03-15T09:30:00+00:00');

        $min = \PHP_INT_MAX;
        $max = 0;
        for ($i = 0; $i < 1_000; ++$i) {
            $tidbit = $factory->createAt($time);
            self::assertSame($time->getTimestamp() * 1000, $tidbit->getTimestamp());
            $counter = $tidbit->toInt() & 0x3FFFFF;
            $min = \min($min, $counter);
            $max = \max($max, $counter);
        }
        // create() keeps counters in the lower half; createAt() has no increments to leave room for
        self::assertGreaterThan(0x3FFFFF >> 1, $max);
        self::assertLessThan(0x3FFFFF >> 1, $min);
    }

    #[Test]
    public function createStaysMonotonicAroundCreateAt(): void
    {
        $factory = new TidbitFactory(clock: new FixedClock(self::NOW), randomizer: new Randomizer(new Mt19937(1)));

        $previous = $factory->create();
        for ($i = 0; $i < 1_000; ++$i) {
            // both directions: far past and past the clock
            $factory->createAt(new FixedClock(Tidbit::EPOCH + $i)->now());
            $factory->createAt(new FixedClock(self::NOW + 1_000_000 + $i)->now());
            $current = $factory->create();
            self::assertSame(1, $current->compare($previous));
            $previous = $current;
        }
        self::assertLessThan(self::NOW + 1_000, $previous->getTimestamp());
    }

    #[Test]
    public function createAtDoesNotAdvanceCreateCounter(): void
    {
        $factory = new TidbitFactory(node: 1, clock: new FixedClock(self::NOW), randomizer: new Randomizer(new Mt19937(1)));

        $first = $factory->create();
        $factory->createAt(new \DateTimeImmutable('2004-03-15T09:30:00+00:00'));
        $second = $factory->create();

        // first counter is at most 2047 of 4095, so the increment cannot overflow
        self::assertSame(1, $second->toInt() - $first->toInt());
    }

    #[Test]
    public function defaultsToSystemClock(): void
    {
        $before = (int) (\microtime(true) * 1000);
        $timestamp = new TidbitFactory()->create()->getTimestamp();

        self::assertGreaterThanOrEqual($before, $timestamp);
        self::assertLessThanOrEqual($before + 1000, $timestamp);
    }
}
