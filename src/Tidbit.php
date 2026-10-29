<?php

namespace Garak\Tidbit;

use Garak\Tidbit\Exception\InvalidTidbitException;

/**
 * Time-sorted identifier stored in 63 bits, so it fits a signed BIGINT.
 *
 * Layout: 41 bits of milliseconds since EPOCH (about 69.7 years, until
 * 2070-09-07), then 22 bits shared between node ID and counter (see TidbitFactory).
 *
 * Inspired by TSID, but not compatible with it: different epoch, bit layout and encoding.
 */
final readonly class Tidbit implements \JsonSerializable, \Stringable
{
    /**
     * 2001-01-01T00:00:00Z in milliseconds. Changing it breaks every existing ID.
     *
     * Start of the third millennium. Far enough back to backfill IDs for
     * pre-existing content (see TidbitFactory::createAt()).
     */
    public const int EPOCH = 978307200000;
    public const int TIME_BITS = 41;
    public const int RANDOM_BITS = 22;

    private function __construct(private int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 0) {
            throw InvalidTidbitException::negative($value);
        }

        return new self($value);
    }

    public static function fromString(string $tidbit): self
    {
        return new self(Base58::decode($tidbit));
    }

    public static function isValid(string $tidbit): bool
    {
        try {
            Base58::decode($tidbit);
        } catch (InvalidTidbitException) {
            return false;
        }

        return true;
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function toString(): string
    {
        return Base58::encode($this->value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Always a string: 63-bit values overflow JavaScript's Number.MAX_SAFE_INTEGER.
     */
    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    /**
     * Unix time in milliseconds.
     */
    public function getTimestamp(): int
    {
        return ($this->value >> self::RANDOM_BITS) + self::EPOCH;
    }

    public function getDateTime(): \DateTimeImmutable
    {
        $ms = $this->getTimestamp();

        return new \DateTimeImmutable(\sprintf('@%d.%03d', \intdiv($ms, 1000), $ms % 1000));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function compare(self $other): int
    {
        return $this->value <=> $other->value;
    }
}
