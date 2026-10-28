<?php

namespace Garak\Tidbit\Exception;

final class InvalidTidbitException extends \InvalidArgumentException
{
    public static function negative(int $value): self
    {
        return new self(\sprintf('Tidbit must be a non-negative integer, got %d.', $value));
    }

    public static function length(string $tidbit, int $expected): self
    {
        return new self(\sprintf('Tidbit "%s" must be %d characters long.', $tidbit, $expected));
    }

    public static function character(string $tidbit, string $char): self
    {
        return new self(\sprintf('Tidbit "%s" contains invalid character "%s".', $tidbit, $char));
    }

    public static function overflow(string $tidbit): self
    {
        return new self(\sprintf('Tidbit "%s" exceeds the 63-bit range.', $tidbit));
    }
}
