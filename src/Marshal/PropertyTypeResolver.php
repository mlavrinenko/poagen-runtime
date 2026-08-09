<?php

declare(strict_types=1);

namespace Poagen\Runtime\Marshal;

use BackedEnum;
use DateTimeInterface;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use Poagen\Runtime\AdditionalPropertyBag;
use Poagen\Runtime\Codec;
use Poagen\Runtime\Contract\PropertyType;
use Poagen\Runtime\ScalarBinding;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function count;
use function in_array;

/**
 * Decides which {@see PropertyType} a constructor parameter carries, given its
 * native reflection type and whatever the `@param` phpdoc added on top.
 *
 * Split from {@see DescriptorParser}, which owns the other half of the job:
 * reflecting a Model, reading its attributes, and caching the result. That half
 * asks a question per parameter; this half answers it, and the two share nothing
 * but the answer's shape.
 *
 * @internal
 */
final class PropertyTypeResolver
{
    /**
     * @param class-string<Codec>|null $codec the class {@see DescriptorParser} read off this
     *                                        parameter's `#[Format]` attribute, when it named one —
     *                                        null for every property no codec binds, which is most
     *                                        of them
     */
    public static function resolve(
        ?ReflectionType $native,
        ?TypeNode $phpdoc,
        ?string $format,
        string $namespace,
        ?string $codec = null,
    ): PropertyType {
        if ($native instanceof ReflectionUnionType) {
            return self::resolveUnion($native);
        }

        if (!$native instanceof ReflectionNamedType) {
            return PropertyType::passthrough();
        }

        $name = $native->getName();

        if ($name === 'array') {
            return self::resolveArrayLike($phpdoc, $namespace, $codec);
        }

        // Checked before `isBuiltin()`, deliberately: a codec whose target is
        // a PHP builtin (`format: byte` → `string`) would otherwise never be
        // seen, because the very next branch would already have answered
        // `passthrough()` for it. An explicit `#[Format(..., codec: ...)]`
        // is generation-time guidance naming this exact property, so it
        // outranks every structural check below it, builtin or class alike.
        if ($codec !== null) {
            return PropertyType::codec($codec);
        }

        if ($native->isBuiltin()) {
            return PropertyType::passthrough();
        }

        return self::resolveClassType($name, $format);
    }

    private static function resolveUnion(ReflectionUnionType $native): PropertyType
    {
        $branches = [];

        foreach ($native->getTypes() as $branch) {
            if (!$branch instanceof ReflectionNamedType) {
                continue;
            }
            if ($branch->isBuiltin()) {
                continue;
            }
            /** @var class-string $branchName */
            $branchName = $branch->getName();
            $branches[] = $branchName;
        }

        // A union of builtins only — `string|int` from a schema that named
        // several scalar types. There is nothing to pick between, so the value
        // passes through as the wire gave it rather than through a branch
        // resolution that has no branches to resolve.
        return $branches === [] ? PropertyType::passthrough() : PropertyType::union($branches);
    }

    private static function resolveClassType(string $name, ?string $format): PropertyType
    {
        /** @var class-string $name */
        if (is_a($name, AdditionalPropertyBag::class, true)) {
            // Not a Model: it has no wire names of its own and is never
            // hydrated field by field. The extras branch of the Hydrator
            // builds it, so it needs a kind of its own rather than falling
            // through to KIND_MODEL, which would go looking for a `values`
            // key in the payload.
            return PropertyType::bag();
        }

        if (is_subclass_of($name, BackedEnum::class)) {
            return PropertyType::enum($name);
        }

        if (is_a($name, DateTimeInterface::class, true)) {
            return PropertyType::dateTime($format ?? 'date-time');
        }

        if (is_a($name, ScalarBinding::class, true)) {
            return PropertyType::scalarBinding($name);
        }

        return PropertyType::model($name);
    }

    /**
     * @param class-string<Codec>|null $codec
     */
    private static function resolveArrayLike(?TypeNode $phpdoc, string $namespace, ?string $codec): PropertyType
    {
        if (!$phpdoc instanceof TypeNode) {
            return PropertyType::passthrough();
        }

        $stripped = self::stripNullable($phpdoc);

        if ($stripped instanceof GenericTypeNode) {
            $head = $stripped->type->name;
            $count = count($stripped->genericTypes);

            if ($head === 'list' && $count > 0) {
                return PropertyType::listOf(self::itemType($stripped->genericTypes[0], $namespace, $codec));
            }

            if ($head === 'array' && $count === 1) {
                return PropertyType::listOf(self::itemType($stripped->genericTypes[0], $namespace, $codec));
            }

            if ($head === 'array' && $count === 2) {
                return PropertyType::mapOf(self::itemType($stripped->genericTypes[1], $namespace, $codec));
            }
        }

        return PropertyType::passthrough();
    }

    /**
     * One list or map item's type. The native reflection type for a
     * `list<T>`/`array<string,T>` property is always plain `array` — PHP
     * carries no generic parameter at that level — so a codec bound to the
     * whole property cannot be read off `$item`'s own reflected class the
     * way {@see resolveClassType()} reads a scalar property's. It is instead
     * threaded straight through from the property's own `#[Format]`
     * attribute: the same codec that governs a bare `T` property governs
     * every item of a `list<T>`/`array<string,T>` one, since one `format`
     * applies to the whole property (`codec-bindings-for-foreign-value-types`).
     *
     * @param class-string<Codec>|null $codec
     */
    private static function itemType(TypeNode $node, string $namespace, ?string $codec): PropertyType
    {
        return $codec !== null ? PropertyType::codec($codec) : self::resolvePhpdocType($node, $namespace);
    }

    private static function stripNullable(TypeNode $node): TypeNode
    {
        if ($node instanceof NullableTypeNode) {
            return $node->type;
        }

        if ($node instanceof UnionTypeNode) {
            $non = array_values(array_filter(
                $node->types,
                static fn(TypeNode $t): bool => !$t instanceof IdentifierTypeNode || $t->name !== 'null',
            ));

            if (count($non) === 1) {
                return $non[0];
            }
        }

        return $node;
    }

    private static function resolvePhpdocType(TypeNode $node, string $namespace): PropertyType
    {
        if ($node instanceof UnionTypeNode) {
            // `Foo|null` collapses to Foo; `Foo|Bar` is treated as a model union.
            $models = [];

            foreach ($node->types as $branch) {
                if ($branch instanceof IdentifierTypeNode && $branch->name === 'null') {
                    continue;
                }
                if ($branch instanceof IdentifierTypeNode) {
                    $resolved = self::resolveIdentifier($branch->name, $namespace);

                    if ($resolved !== null) {
                        $models[] = $resolved;
                    }
                }
            }

            return $models === [] ? PropertyType::passthrough() : PropertyType::union($models);
        }

        if ($node instanceof IdentifierTypeNode) {
            $class = self::resolveIdentifier($node->name, $namespace);

            return $class === null ? PropertyType::passthrough() : self::resolveClassType($class, null);
        }

        return PropertyType::passthrough();
    }

    /**
     * @return class-string|null
     */
    private static function resolveIdentifier(string $name, string $namespace): ?string
    {
        if ($name === '' || in_array($name, ['string', 'int', 'integer', 'bool', 'boolean', 'float', 'mixed', 'array', 'null'], true)) {
            return null;
        }

        $candidate = ltrim($name, '\\');

        if (class_exists($candidate, false) || interface_exists($candidate, false) || enum_exists($candidate, false)) {
            /** @var class-string $candidate */
            return $candidate;
        }

        $namespaced = ltrim($namespace . '\\' . $candidate, '\\');

        if (class_exists($namespaced) || interface_exists($namespaced) || enum_exists($namespaced)) {
            /** @var class-string $namespaced */
            return $namespaced;
        }

        return class_exists($candidate) ? $candidate : null;
    }
}
