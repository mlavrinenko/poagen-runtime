<?php

declare(strict_types=1);

namespace Poagen\Runtime\Format;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function assert;

/**
 * Binds `format: uuid` to `ramsey/uuid`. Selected from a profile, never by
 * default:
 *
 * ```php
 * new Profile(
 *     // ...
 *     formats: ['uuid' => \Poagen\Runtime\Format\RamseyUuidCodec::class],
 * );
 * ```
 *
 * The emitted property is `UuidInterface` directly — {@see UuidCodec}
 * carries why this is a candidate rather than a built-in, and why a codec
 * rather than a wrapper.
 */
final readonly class RamseyUuidCodec extends UuidCodec
{
    public static function targetType(): string
    {
        return UuidInterface::class;
    }

    public static function decode(mixed $wire): UuidInterface
    {
        return Uuid::fromString(self::requireString($wire));
    }

    /**
     * The canonical RFC 4122 form, which is what `format: uuid` means and
     * what has to go back on the wire.
     */
    public static function encode(mixed $value): string
    {
        assert($value instanceof UuidInterface);

        return $value->toString();
    }
}
