<?php

declare(strict_types=1);

namespace Poagen\Runtime\Marshal;

use RuntimeException;

use function count;
use function implode;
use function sprintf;

/**
 * Raised when hydrating a union-typed property cannot name exactly one branch.
 *
 * Every constructor here is a case the generator deliberately left to runtime.
 * `declare-an-undiscriminated-union-as-raw-data` makes generation-time branch
 * selection safe precisely by refusing to resolve these: a payload matching no
 * branch, or two, is an exception rather than a wrong object. The alternative —
 * taking the first match — produces a fully typed value that is silently the
 * wrong class, which the consumer discovers in production rather than here.
 */
final class UnionBranchException extends RuntimeException
{
    /**
     * @param list<string> $branches
     * @param list<string> $keys
     */
    public static function noMatch(string $property, array $branches, array $keys): self
    {
        return new self(sprintf(
            "Hydrating property '%s': the payload matched none of the union branches %s. The payload carries %s. "
                . 'A union property never guesses a branch, so this stops rather than constructing one of them.',
            $property,
            self::names($branches),
            self::keys($keys),
        ));
    }

    /**
     * @param list<string> $matched
     * @param list<string> $keys
     */
    public static function ambiguous(string $property, array $matched, array $keys): self
    {
        return new self(sprintf(
            "Hydrating property '%s': the payload matched %d union branches at once (%s). The payload carries %s. "
                . 'Each of those branches declares a field none of the others do, so a payload carrying several of '
                . 'them names no single branch and this stops rather than picking one.',
            $property,
            count($matched),
            self::names($matched),
            self::keys($keys),
        ));
    }

    /**
     * @param list<string> $mapped
     */
    public static function tagAbsent(string $property, string $field, bool $onParent, array $mapped): self
    {
        return new self(sprintf(
            "Hydrating property '%s': the discriminator field '%s' is absent from %s, so no branch is named. "
                . 'One of %s was expected.',
            $property,
            $field,
            $onParent ? 'the enclosing payload' : 'the payload',
            self::names($mapped),
        ));
    }

    /**
     * @param list<string> $mapped
     */
    public static function tagUnmapped(string $property, string $field, string $tag, array $mapped): self
    {
        return new self(sprintf(
            "Hydrating property '%s': the discriminator field '%s' carries '%s', which no branch is mapped to. "
                . 'The mapped values are %s. A value the document permits but the mapping does not name stops here '
                . 'rather than falling back to a branch nobody chose.',
            $property,
            $field,
            $tag,
            self::names($mapped),
        ));
    }

    /**
     * @param list<string> $names
     */
    private static function names(array $names): string
    {
        return $names === [] ? '(none)' : implode(', ', $names);
    }

    /**
     * @param list<string> $keys
     */
    private static function keys(array $keys): string
    {
        return $keys === [] ? 'no keys at all' : 'the keys ' . implode(', ', $keys);
    }
}
