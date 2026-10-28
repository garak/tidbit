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
    public function defaultsToSystemClock(): void
    {
        $before = (int) (\microtime(true) * 1000);
        $timestamp = new TidbitFactory()->create()->getTimestamp();

        self::assertGreaterThanOrEqual($before, $timestamp);
        self::assertLessThanOrEqual($before + 1000, $timestamp);
    }
}
