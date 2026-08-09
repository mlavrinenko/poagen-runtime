<?php

declare(strict_types=1);

namespace Poagen\Runtime\Format;

use InvalidArgumentException;
use Poagen\Runtime\Codec;

use function assert;
use function base64_decode;
use function base64_encode;
use function get_debug_type;
use function is_string;
use function sprintf;

/**
 * Binds `format: byte` — OpenAPI's spelling for base64-encoded bytes inside
 * a JSON string. Unlike `format: uuid`, this one is a poagen built-in: both
 * directions are PHP core (`base64_decode()` / `base64_encode()`) and the
 * decoded value is a plain `string`, so there is no library to choose and no
 * dependency a default binding could cost a consumer
 * (`base64-binding-for-format-byte`). `Generator\Bindings::scalarFormat()`
 * wires this in as the fallback for `byte`, exactly where `date-time`/`date`
 * wire in `\DateTimeImmutable` — a `Profile::$formats['byte']` entry,
 * a `$propertyFormats` one, or a discovered `#[BindsFormat('byte')]` value
 * object still wins over it, the same precedence any other built-in loses to.
 */
final readonly class Base64Codec implements Codec
{
    public static function targetType(): string
    {
        return 'string';
    }

    /**
     * Strict mode throughout: a non-string payload, or a string with a
     * character outside the base64 alphabet or padding in the wrong place,
     * is refused rather than decoded into corrupted bytes — which is what
     * PHP's lenient `base64_decode()` mode would do, silently.
     */
    public static function decode(mixed $wire): string
    {
        if (!is_string($wire)) {
            throw new InvalidArgumentException(
                'format: byte expects a string payload, got ' . get_debug_type($wire),
            );
        }

        $decoded = base64_decode($wire, true);

        if ($decoded === false) {
            throw new InvalidArgumentException(
                sprintf("format: byte expects a base64-encoded string, got '%s'", $wire),
            );
        }

        return $decoded;
    }

    public static function encode(mixed $value): string
    {
        assert(is_string($value));

        return base64_encode($value);
    }
}
