<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests\Fixtures;

use Poagen\Runtime\ScalarBinding;

use function assert;
use function is_string;

final readonly class Email implements ScalarBinding
{
    public function __construct(public string $value) {}

    public static function fromScalar(mixed $raw): self
    {
        assert(is_string($raw));

        return new self($raw);
    }

    public function toScalar(): string
    {
        return $this->value;
    }
}
