<?php

declare(strict_types=1);

namespace Poagen\Runtime\Marshal;

use BackedEnum;
use DateTimeImmutable;
use LogicException;
use Poagen\Runtime\AdditionalPropertyBag;
use Poagen\Runtime\Codec;
use Poagen\Runtime\Contract\DescriptorParserInterface;
use Poagen\Runtime\Contract\HydratorInterface;
use Poagen\Runtime\Contract\PropertyDescriptor;
use Poagen\Runtime\Contract\PropertyType;
use Poagen\Runtime\ScalarBinding;

use function array_key_exists;
use function assert;
use function is_array;
use function is_int;
use function is_string;

/**
 * Inverse of {@see Marshaller}: turns a JSON-decoded array into a typed
 * Model instance. Recurses into nested models, lists, maps, and
 * discriminated / structural unions. See
 * `attribute-and-phpdoc-driven-serialization`.
 */
final readonly class Hydrator implements HydratorInterface
{
    public function __construct(
        private DescriptorParserInterface $descriptors = new DescriptorParser(),
    ) {}

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<mixed>    $data
     *
     * @return T
     */
    public function hydrate(string $class, array $data): object
    {
        $args = [];
        $claimed = [];
        $extras = null;

        foreach ($this->descriptors->descriptorsFor($class) as $descriptor) {
            if ($descriptor->additional) {
                $extras = $descriptor;
                continue;
            }

            $claimed[$descriptor->wireName] = true;

            if (!array_key_exists($descriptor->wireName, $data)) {
                continue;
            }

            $args[$descriptor->phpName] = $this->decode($data[$descriptor->wireName], $descriptor->type, $descriptor, $data);
        }

        if ($extras instanceof PropertyDescriptor) {
            $args[$extras->phpName] = $this->extras($data, $claimed, $extras);
        }

        return new $class(...$args);
    }

    /**
     * Every key no declared property claimed, decoded as the schema typed the
     * extra values. A declared property always wins the key it names, so the
     * extras never shadow one: `$claimed` is filled from every declared
     * descriptor before this runs, whether or not the payload carried that key.
     *
     * Two carriers, one collection pass. A schema that typed its extra values
     * gets the typed map `declared-shapes-become-types` asks for; a schema
     * that only said `additionalProperties: true` gets
     * {@see AdditionalPropertyBag}, because there is no declared value type to
     * make a typed map out of.
     *
     * @param array<mixed>       $data
     * @param array<string,true> $claimed
     *
     * @return array<string, mixed>|AdditionalPropertyBag
     */
    private function extras(array $data, array $claimed, PropertyDescriptor $descriptor): array|AdditionalPropertyBag
    {
        $valueType = $descriptor->type->itemType ?? PropertyType::passthrough();
        $out = [];

        foreach ($data as $key => $value) {
            if (!isset($claimed[(string) $key])) {
                $out[(string) $key] = $this->decode($value, $valueType);
            }
        }

        return $descriptor->type->kind === PropertyType::KIND_BAG ? new AdditionalPropertyBag($out) : $out;
    }

    /**
     * `$owner` and `$parent` travel together and only matter to a union: the
     * descriptor carries the branch-selection rule the generator computed, and
     * `$parent` is the payload that rule reads when the tag sits on a sibling
     * rather than inside the value. Both are passed down through lists and
     * maps unchanged, so a `list<Dog|Cat>` resolves by the same rule its
     * property declared rather than by a different one a level down.
     *
     * @param array<mixed> $parent
     */
    private function decode(mixed $value, PropertyType $type, ?PropertyDescriptor $owner = null, array $parent = []): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type->kind) {
            PropertyType::KIND_PASSTHROUGH => $value,
            PropertyType::KIND_DATETIME => $this->decodeDateTime($value),
            PropertyType::KIND_ENUM => $this->decodeEnum($value, $type),
            PropertyType::KIND_MODEL => $this->decodeModel($value, $type),
            PropertyType::KIND_SCALAR_BINDING => $this->decodeScalarBinding($value, $type),
            PropertyType::KIND_CODEC => $this->decodeCodec($value, $type),
            PropertyType::KIND_UNION => $this->decodeUnion($value, $owner, $parent),
            PropertyType::KIND_LIST => $this->decodeList($value, $type, $owner, $parent),
            PropertyType::KIND_MAP => $this->decodeMap($value, $type, $owner, $parent),
            default => $value,
        };
    }

    private function decodeDateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        assert(is_string($value));

        return new DateTimeImmutable($value);
    }

    private function decodeEnum(mixed $value, PropertyType $type): BackedEnum
    {
        /** @var class-string<BackedEnum> $class */
        $class = $type->class;

        if ($value instanceof $class) {
            return $value;
        }

        assert(is_string($value) || is_int($value));

        return $class::from($value);
    }

    private function decodeModel(mixed $value, PropertyType $type): object
    {
        $class = $type->class;
        assert(is_string($class));

        if ($value instanceof $class) {
            return $value;
        }

        assert(is_array($value));

        return $this->hydrate($class, $value);
    }

    private function decodeScalarBinding(mixed $value, PropertyType $type): ScalarBinding
    {
        /** @var class-string<ScalarBinding> $class */
        $class = $type->class;

        if ($value instanceof $class) {
            return $value;
        }

        return $class::fromScalar($value);
    }

    /**
     * The foreign-type sibling of {@see decodeScalarBinding()}: `$type->class`
     * names the {@see Codec}, never the type it targets, so the "already
     * decoded" check compares against `$codec::targetType()` rather than
     * against `$type->class` itself — the wire value is never an instance of
     * the codec. A value already shaped as the target type is returned as
     * is, so a caller that constructs a Model from already-typed values
     * (rather than from raw wire data) is not forced to encode and
     * immediately decode.
     */
    private function decodeCodec(mixed $value, PropertyType $type): mixed
    {
        /** @var class-string<Codec> $codec */
        $codec = $type->class;
        $target = $codec::targetType();

        if ($value instanceof $target) {
            return $value;
        }

        return $codec::decode($value);
    }

    /**
     * Exactly one branch, or an exception. {@see UnionBranchPicker} holds the
     * rule; what matters here is that there is no third outcome — no first
     * branch taken as a fallback, no null slipping through as the decoded
     * value. `declare-an-undiscriminated-union-as-raw-data` makes computing a
     * branch at generation time safe precisely by making the leftovers loud.
     *
     * @param array<mixed> $parent
     */
    private function decodeUnion(mixed $value, ?PropertyDescriptor $owner, array $parent): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!$owner instanceof PropertyDescriptor) {
            throw new LogicException('union descriptor reached the hydrator without its property descriptor');
        }

        return $this->hydrate(UnionBranchPicker::pick($value, $parent, $owner), $value);
    }

    /**
     * @param array<mixed> $parent
     *
     * @return list<mixed>
     */
    private function decodeList(mixed $value, PropertyType $type, ?PropertyDescriptor $owner, array $parent): array
    {
        assert(is_array($value));
        $itemType = $type->itemType ?? PropertyType::passthrough();
        $out = [];

        foreach ($value as $item) {
            $out[] = $this->decode($item, $itemType, $owner, $parent);
        }

        return $out;
    }

    /**
     * @param array<mixed> $parent
     *
     * @return array<string, mixed>
     */
    private function decodeMap(mixed $value, PropertyType $type, ?PropertyDescriptor $owner, array $parent): array
    {
        assert(is_array($value));
        $valueType = $type->itemType ?? PropertyType::passthrough();
        $out = [];

        foreach ($value as $key => $item) {
            $out[(string) $key] = $this->decode($item, $valueType, $owner, $parent);
        }

        return $out;
    }
}
