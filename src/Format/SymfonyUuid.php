<?php

declare(strict_types=1);

namespace Poagen\Runtime\Format;

use Symfony\Component\Uid\Uuid;

/**
 * Binds `format: uuid` to `symfony/uid`. Selected from a profile, never by
 * default; see {@see RamseyUuid} for the shape and {@see UuidBinding} for
 * why neither is a built-in.
 *
 * `toScalar()` spells RFC 4122 out rather than deferring to the library's
 * own string cast. `symfony/uid` accepts base58 and base32 on the way in and
 * its cast is configurable, so a wire form that follows whatever the
 * consumer's container happens to be set to is a payload that changes shape
 * without the schema changing.
 */
final readonly class SymfonyUuid extends UuidBinding
{
    public function __construct(public Uuid $value) {}

    public static function fromScalar(mixed $raw): self
    {
        return new self(Uuid::fromString(self::requireString($raw)));
    }

    public function toScalar(): string
    {
        return $this->value->toRfc4122();
    }
}
