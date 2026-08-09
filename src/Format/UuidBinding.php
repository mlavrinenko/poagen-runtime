<?php

declare(strict_types=1);

namespace Poagen\Runtime\Format;

use InvalidArgumentException;
use Poagen\Runtime\Marshal\PropertyTypeResolver;
use Poagen\Runtime\ScalarBinding;
use Stringable;

use function get_debug_type;
use function is_string;

/**
 * What the shipped `format: uuid` bindings share: the payload guard, the
 * string cast, and the reason they exist at all.
 *
 * poagen binds `uuid` to nothing by default. `date-time` was free — PHP core
 * ships `DateTimeImmutable`, so binding it cost no consumer a dependency and
 * took no side on which library is right — and `uuid` has no core type, so
 * any binding for it is a dependency decision. {@see RamseyUuid} and
 * {@see SymfonyUuid} are therefore candidates rather than defaults, their
 * libraries `suggest` rather than `require`, and a `Profile::$formats` entry
 * naming one is what turns it on. A consumer on a third library subclasses
 * this the same way, or writes their own {@see ScalarBinding} from scratch.
 *
 * A wrapper rather than the library's UUID type itself, because
 * {@see PropertyTypeResolver} recognises a binding by reflecting the
 * property's declared type and testing it against {@see ScalarBinding} — an
 * interface a third-party class cannot be made to implement. Reading the
 * library value therefore costs one hop, `$model->id->value`, or
 * `(string) $model->id` when the canonical form is all that is wanted.
 * Removing that hop needs a codec kind that converts for a foreign type
 * instead of asking the type to convert itself; see the generator's
 * `codec-bindings-for-foreign-value-types`.
 */
abstract readonly class UuidBinding implements ScalarBinding, Stringable
{
    /**
     * The canonical RFC 4122 form, which is what `format: uuid` means and
     * what every subclass has to put back on the wire — never the library's
     * own string cast, which each of them defines for itself and at least
     * one of them lets the consumer configure.
     */
    abstract public function toScalar(): string;

    public function __toString(): string
    {
        return $this->toScalar();
    }

    /**
     * Rejects a non-string payload before it reaches the library, whose own
     * message would name the library instead of the wire value that was
     * actually wrong. A malformed string is left to the library: every
     * candidate here already throws `InvalidArgumentException` for one and
     * already quotes the offending input.
     */
    protected static function requireString(mixed $raw): string
    {
        if (!is_string($raw)) {
            throw new InvalidArgumentException(
                'format: uuid expects a string payload, got ' . get_debug_type($raw),
            );
        }

        return $raw;
    }
}
