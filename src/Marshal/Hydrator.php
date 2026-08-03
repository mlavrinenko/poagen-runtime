<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Marshal;

use BackedEnum;
use DateTimeImmutable;
use LogicException;
use Mlavrinenko\Paogen\Runtime\AdditionalPropertyBag;
use Mlavrinenko\Paogen\Runtime\Attribute\Discriminator;
use Mlavrinenko\Paogen\Runtime\Contract\DescriptorParserInterface;
use Mlavrinenko\Paogen\Runtime\Contract\HydratorInterface;
use Mlavrinenko\Paogen\Runtime\Contract\PropertyDescriptor;
use Mlavrinenko\Paogen\Runtime\Contract\PropertyType;
use Mlavrinenko\Paogen\Runtime\ScalarBinding;

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

            $args[$descriptor->phpName] = $this->decode($data[$descriptor->wireName], $descriptor->type, $descriptor->discriminator);
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

    private function decode(mixed $value, PropertyType $type, ?Discriminator $discriminator = null): mixed
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
            PropertyType::KIND_UNION => $this->decodeUnion($value, $type, $discriminator),
            PropertyType::KIND_LIST => $this->decodeList($value, $type),
            PropertyType::KIND_MAP => $this->decodeMap($value, $type),
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

    private function decodeUnion(mixed $value, PropertyType $type, ?Discriminator $discriminator): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        // attribute-and-phpdoc-driven-serialization + T-030: every union
        // descriptor is discriminated — the generator refuses
        // undiscriminated ref unions, so this branch is always taken for
        // valid inputs.
        assert($discriminator instanceof Discriminator, 'union descriptor missing #[Discriminator] (attribute-and-phpdoc-driven-serialization)');

        $branch = $this->branchFromDiscriminator($value, $discriminator);

        if ($branch === null) {
            $branch = $type->branches[0] ?? throw new LogicException('union descriptor has no branches');
        }

        return $this->hydrate($branch, $value);
    }

    /**
     * @param array<mixed> $value
     *
     * @return class-string|null
     */
    private function branchFromDiscriminator(array $value, Discriminator $discriminator): ?string
    {
        if (!array_key_exists($discriminator->field, $value)) {
            return null;
        }

        $tag = $value[$discriminator->field];

        if (!is_string($tag)) {
            return null;
        }

        return $discriminator->map[$tag] ?? null;
    }

    /**
     * @return list<mixed>
     */
    private function decodeList(mixed $value, PropertyType $type): array
    {
        assert(is_array($value));
        $itemType = $type->itemType ?? PropertyType::passthrough();
        $out = [];

        foreach ($value as $item) {
            $out[] = $this->decode($item, $itemType);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMap(mixed $value, PropertyType $type): array
    {
        assert(is_array($value));
        $valueType = $type->itemType ?? PropertyType::passthrough();
        $out = [];

        foreach ($value as $key => $item) {
            $out[(string) $key] = $this->decode($item, $valueType);
        }

        return $out;
    }
}
