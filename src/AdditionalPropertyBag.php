<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Mlavrinenko\Paogen\Runtime\Attribute\AdditionalProperties;
use Traversable;

use function array_key_exists;
use function array_keys;
use function count;

/**
 * The keys a payload carried that the schema allowed without naming — the
 * `additionalProperties: true` case, where the document permitted extras and
 * said nothing about their shape.
 *
 * Where the document *did* give a schema for the extra values they are typed,
 * and a typed map is what the emitted property carries: `array<string, Part>`
 * is a collection of a declared shape, which `declared-shapes-become-types`
 * keeps as a PHP array. This class is for the other case, and it exists
 * because `array<string, mixed>` there would hand the consumer back the
 * parsing job they came to be rid of. A bag answers questions; an array has to
 * be guarded before it can be asked one.
 *
 * Nothing here validates. `additionalProperties: true` is the document
 * declining to constrain the values, so a value comes back exactly as the wire
 * delivered it and in the order it arrived — which is what makes a hydrate /
 * marshal round trip byte-stable.
 *
 * Immutable, like every other emitted value: there is no setter, and the
 * backing array is copied in by value on construction.
 *
 * The property carrying one is marked {@see AdditionalProperties}, the same
 * marker the typed-map form uses. There is one mechanism for extras, not two.
 *
 * @implements IteratorAggregate<string, mixed>
 */
final readonly class AdditionalPropertyBag implements Countable, IteratorAggregate
{
    /**
     * @param array<string, mixed> $values keyed by wire name, in arrival order
     */
    public function __construct(private array $values = []) {}

    /**
     * Whether the payload carried this key at all — the question `get()`
     * cannot answer on its own, since a key present with a null value and an
     * absent key both read back as null.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * The value the payload carried under $key, or $default when it carried
     * no such key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    /**
     * @return list<string> the undeclared keys, in arrival order
     */
    public function keys(): array
    {
        return array_keys($this->values);
    }

    public function count(): int
    {
        return count($this->values);
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /**
     * @return Traversable<string, mixed>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values);
    }

    /**
     * The extras as the wire had them. This is what the marshaller spreads
     * back out beside the declared properties, and it is the escape hatch for
     * a consumer who genuinely wants the array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
