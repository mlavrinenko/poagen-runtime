<?php

declare(strict_types=1);

namespace Poagen\Runtime\Format;

use InvalidArgumentException;
use Poagen\Runtime\Codec;

use function get_debug_type;
use function is_string;

/**
 * What the shipped `format: uuid` codecs share: the payload guard, and the
 * reason they are codecs rather than a wrapper value object.
 *
 * poagen binds `uuid` to nothing by default. `date-time` was free — PHP core
 * ships `DateTimeImmutable`, so binding it cost no consumer a dependency and
 * took no side on which library is right — and `uuid` has no core type, so
 * any binding for it is a dependency decision. {@see RamseyUuidCodec} and
 * {@see SymfonyUuidCodec} are therefore candidates rather than defaults,
 * their libraries `suggest` rather than `require`, and a
 * `Profile::$formats` entry naming one is what turns it on. A consumer on a
 * third library subclasses this the same way, or writes their own
 * {@see Codec} from scratch.
 *
 * Earlier revisions of this binding were a wrapper implementing
 * `ScalarBinding` — `Format\RamseyUuid` holding the library's `UuidInterface`
 * one hop in, `$model->id->value`. `codec-bindings-for-foreign-value-types`
 * replaced that: `Marshal\PropertyTypeResolver` used to be able to recognise
 * a binding only by reflecting the property's own declared type, which the
 * library's `UuidInterface` — a type poagen does not own — could never
 * satisfy. A {@see Codec} converts on the type's behalf instead, so the
 * property is declared as `UuidInterface` directly and the hop is gone; see
 * `truth/decisions/reexpress-the-uuid-bindings-as-codecs.decision-260809.typ`.
 */
abstract readonly class UuidCodec implements Codec
{
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
