<?php

namespace Garak\Tidbit;

use Psr\Clock\ClockInterface;
use Random\Randomizer;

/**
 * Generates Tidbits that increase strictly within one factory instance.
 *
 * The 22 low bits are split between node ID and counter. The counter starts at
 * a random value on each new millisecond, so separate processes (e.g. PHP-FPM
 * workers) sharing a node ID rarely collide without any coordination.
 */
final class TidbitFactory
{
    public const int DEFAULT_NODE_BITS = 10;
    private const int MAX_NODE_BITS = 20;
    private const int MAX_TIME = (1 << Tidbit::TIME_BITS) - 1;

    private readonly int $node;
    private readonly int $counterBits;
    private readonly int $counterMax;
    private int $lastTime = -1;
    private int $counter = 0;

    /**
     * @param int|null $node     fixed node ID; random per instance when null
     * @param int      $nodeBits bits for the node ID (0-20); the rest of the 22 go to the counter
     */
    public function __construct(
        ?int $node = null,
        int $nodeBits = self::DEFAULT_NODE_BITS,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly Randomizer $randomizer = new Randomizer(),
    ) {
        if ($nodeBits < 0 || $nodeBits > self::MAX_NODE_BITS) {
            throw new \InvalidArgumentException(\sprintf('Node bits must be between 0 and %d, got %d.', self::MAX_NODE_BITS, $nodeBits));
        }
        $maxNode = (1 << $nodeBits) - 1;
        if (null === $node) {
            $node = $this->randomizer->getInt(0, $maxNode);
        } elseif ($node < 0 || $node > $maxNode) {
            throw new \InvalidArgumentException(\sprintf('Node must be between 0 and %d, got %d.', $maxNode, $node));
        }
        $this->node = $node;
        $this->counterBits = Tidbit::RANDOM_BITS - $nodeBits;
        $this->counterMax = (1 << $this->counterBits) - 1;
    }

    public function create(): Tidbit
    {
        $time = (int) $this->clock->now()->format('Uv') - Tidbit::EPOCH;
        if ($time < 0) {
            throw new \OutOfRangeException('Clock is set before the Tidbit epoch.');
        }

        if ($time > $this->lastTime) {
            $this->lastTime = $time;
            $this->counter = $this->randomCounter();
        } elseif (++$this->counter > $this->counterMax) {
            // Counter exhausted, or clock went backwards: borrow the next
            // millisecond instead of waiting, so IDs stay unique and increasing.
            ++$this->lastTime;
            $this->counter = $this->randomCounter();
        }

        if ($this->lastTime > self::MAX_TIME) {
            throw new \OverflowException('Tidbit time range exhausted.');
        }

        return Tidbit::fromInt(
            ($this->lastTime << Tidbit::RANDOM_BITS)
            | ($this->node << $this->counterBits)
            | $this->counter
        );
    }

    public function getNode(): int
    {
        return $this->node;
    }

    /**
     * Leaves the upper half free, so the counter can still increment within the millisecond.
     */
    private function randomCounter(): int
    {
        return $this->randomizer->getInt(0, $this->counterMax >> 1);
    }
}
