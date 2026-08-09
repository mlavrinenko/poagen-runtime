<?php

declare(strict_types=1);

namespace Poagen\Runtime\Format;

use Symfony\Component\Uid\Uuid;

use function assert;

/**
 * Binds `format: uuid` to `symfony/uid`. Selected from a profile, never by
 * default; see {@see RamseyUuidCodec} for the shape and {@see UuidCodec} for
 * why neither is a built-in.
 *
 * `encode()` spells RFC 4122 out rather than deferring to the library's own
 * string cast. `symfony/uid` accepts base58 and base32 on the way in and its
 * cast is configurable, so a wire form that follows whatever the consumer's
 * container happens to be set to is a payload that changes shape without
 * the schema changing.
 */
final readonly class SymfonyUuidCodec extends UuidCodec
{
    public static function targetType(): string
    {
        return Uuid::class;
    }

    public static function decode(mixed $wire): Uuid
    {
        return Uuid::fromString(self::requireString($wire));
    }

    public static function encode(mixed $value): string
    {
        assert($value instanceof Uuid);

        return $value->toRfc4122();
    }
}
