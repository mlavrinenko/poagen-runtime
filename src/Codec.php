<?php

declare(strict_types=1);

namespace Poagen\Runtime;

/**
 * Converts a wire scalar to and from a type poagen does not control — a
 * value object from a third-party library that cannot be made to implement
 * {@see ScalarBinding} the way a consumer's own value object can, because
 * that would mean editing a class the consumer does not own.
 *
 * Where a {@see ScalarBinding} converts itself (`fromScalar()` / `toScalar()`
 * live on the value object the property is typed as), a codec converts on
 * behalf of a type it does not own: the emitted property is declared as
 * {@see targetType()} itself — `public UuidInterface $id`, never a wrapper —
 * and {@see decode()} / {@see encode()} carry the round-trip the type cannot
 * carry for itself. See `codec-bindings-for-foreign-value-types`.
 */
interface Codec
{
    /**
     * The PHP type {@see decode()} returns and {@see encode()} accepts — a
     * class-string for a foreign object type, or one of PHP's own scalar
     * type names (`'string'`, `'int'`, `'float'`, `'bool'`) when the target
     * is already a builtin, as `format: byte` in `base64-binding-for-
     * format-byte` targets `'string'`.
     */
    public static function targetType(): string;

    /**
     * The wire value, decoded into {@see targetType()}. Rejects a payload
     * that cannot round-trip rather than returning a value that silently
     * corrupts on the way back out.
     */
    public static function decode(mixed $wire): mixed;

    /**
     * An instance of {@see targetType()}, encoded back to the wire scalar.
     */
    public static function encode(mixed $value): mixed;
}
