<?php

declare(strict_types=1);

namespace Poagen\Runtime\Marshal;

use Poagen\Runtime\Attribute\BranchSelector;
use Poagen\Runtime\Attribute\Discriminator;
use Poagen\Runtime\Contract\PropertyDescriptor;

use function array_key_exists;
use function array_keys;
use function array_values;
use function count;
use function is_string;

/**
 * Names the one union branch a payload selects, or raises.
 *
 * Two mechanisms, in the order the generator resolved them: a tag, read either
 * from the value or from the payload that declares the property; or the
 * presence of a field only one branch declares. Both are decided at generation
 * time and carried here as data — nothing is inferred from the payload beyond
 * what the attribute already says to look for.
 *
 * The one rule that makes the whole design safe lives here: this returns
 * exactly one branch or it throws. It never returns a "best" branch, and there
 * is deliberately no tie-break — see the non-goals in
 * `declare-an-undiscriminated-union-as-raw-data`. Split from {@see Hydrator} so
 * that rule sits in a file a reader can hold in their head, and so the
 * Hydrator stays under the linecop limit.
 *
 * @internal
 */
final readonly class UnionBranchPicker
{
    /**
     * @param array<mixed> $value  the payload being hydrated into a branch
     * @param array<mixed> $parent the payload of the object declaring the property
     *
     * @return class-string
     */
    public static function pick(array $value, array $parent, PropertyDescriptor $owner): string
    {
        $discriminator = $owner->discriminator;

        if ($discriminator instanceof Discriminator) {
            return self::byTag($value, $parent, $owner->wireName, $discriminator);
        }

        $selector = $owner->selector;

        if ($selector instanceof BranchSelector) {
            return self::byStructure($value, $owner->wireName, $selector);
        }

        throw UnionBranchException::noMatch($owner->wireName, $owner->type->branches, self::keysOf($value));
    }

    /**
     * @param array<mixed> $value
     * @param array<mixed> $parent
     *
     * @return class-string
     */
    private static function byTag(array $value, array $parent, string $property, Discriminator $discriminator): string
    {
        $source = $discriminator->onParent ? $parent : $value;
        $mapped = array_keys($discriminator->map);

        if (!array_key_exists($discriminator->field, $source)) {
            throw UnionBranchException::tagAbsent($property, $discriminator->field, $discriminator->onParent, $mapped);
        }

        $tag = $source[$discriminator->field];

        if (!is_string($tag)) {
            throw UnionBranchException::tagAbsent($property, $discriminator->field, $discriminator->onParent, $mapped);
        }

        return $discriminator->map[$tag]
            ?? throw UnionBranchException::tagUnmapped($property, $discriminator->field, $tag, $mapped);
    }

    /**
     * A branch is selected by the mere presence of a key only it declares. An
     * empty payload selects the closed branch when the union has one, and
     * nothing otherwise — no field can be present in `{}`, so without a closed
     * branch an empty payload correctly matches none.
     *
     * @param array<mixed> $value
     *
     * @return class-string
     */
    private static function byStructure(array $value, string $property, BranchSelector $selector): string
    {
        /** @var array<class-string,true> $matched */
        $matched = [];

        foreach ($selector->fields as $field => $branch) {
            if (array_key_exists($field, $value)) {
                $matched[$branch] = true;
            }
        }

        if ($value === [] && $selector->empty !== null) {
            $matched[$selector->empty] = true;
        }

        /** @var list<class-string> $branches */
        $branches = array_keys($matched);

        if (count($branches) === 1) {
            return $branches[0];
        }

        if ($branches === []) {
            throw UnionBranchException::noMatch($property, self::candidates($selector), self::keysOf($value));
        }

        throw UnionBranchException::ambiguous($property, $branches, self::keysOf($value));
    }

    /**
     * @return list<string>
     */
    private static function candidates(BranchSelector $selector): array
    {
        $out = array_values($selector->fields);

        if ($selector->empty !== null) {
            $out[] = $selector->empty;
        }

        return $out;
    }

    /**
     * @param array<mixed> $value
     *
     * @return list<string>
     */
    private static function keysOf(array $value): array
    {
        $out = [];

        foreach (array_keys($value) as $key) {
            $out[] = (string) $key;
        }

        return $out;
    }
}
