<?php

namespace Garak\Tidbit\Tests;

use Psr\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(public int $milliseconds)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('@%d.%03d', \intdiv($this->milliseconds, 1000), $this->milliseconds % 1000));
    }
}
