<?php

declare(strict_types=1);

namespace Poagen\Runtime\Marshal;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;

use function assert;
use function is_array;
use function is_object;

/**
 * Walks a Model instance, produces a JSON-ready array keyed by wire names.
 *
 * Reads class metadata via {@see DescriptorParser} (native reflection +
 * phpdoc + attributes); see `attribute-and-phpdoc-driven-serialization`. The
 * result is intended to be passed
 * directly to `json_encode(..., JSON_THROW_ON_ERROR)`.
 */
final readonly class Marshaller implements \Poagen\Runtime\Contract\MarshallerInterface
{
    public function __construct(
        private \Poagen\Runtime\Contract\DescriptorParserInterface $descriptors = new DescriptorParser(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function marshal(object $model): array
    {
        $out = [];
        $reflection = new ReflectionClass($model);

        $extras = [];

        foreach ($this->descriptors->descriptorsFor($model::class) as $descriptor) {
            $value = $reflection->getProperty($descriptor->phpName)->getValue($model);

            // Extra keys are keys of the payload, not one key holding a map,
            // so they go back out beside the declared properties rather than
            // nested under a name of their own. A bag unwraps to the array it
            // was built from, in arrival order, which is what makes the round
            // trip stable rather than merely lossless.
            if ($descriptor->additional) {
                $extras = $this->encodeMap(
                    $value instanceof \Poagen\Runtime\AdditionalPropertyBag ? $value->toArray() : $value,
                    $descriptor->type,
                );
                continue;
            }

            $out[$descriptor->wireName] = $this->encode($value, $descriptor->type);
        }

        // `+` and not a merge: a declared property owns its wire name, so an
        // extra key that somehow carries the same one cannot overwrite it.
        return $out + $extras;
    }

    private function encode(mixed $value, \Poagen\Runtime\Contract\PropertyType $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type->kind) {
            \Poagen\Runtime\Contract\PropertyType::KIND_PASSTHROUGH => $value,
            \Poagen\Runtime\Contract\PropertyType::KIND_DATETIME => $this->encodeDateTime($value, $type),
            \Poagen\Runtime\Contract\PropertyType::KIND_ENUM => $this->encodeEnum($value),
            \Poagen\Runtime\Contract\PropertyType::KIND_MODEL, \Poagen\Runtime\Contract\PropertyType::KIND_UNION => $this->encodeModel($value),
            \Poagen\Runtime\Contract\PropertyType::KIND_SCALAR_BINDING => $this->encodeScalarBinding($value),
            \Poagen\Runtime\Contract\PropertyType::KIND_LIST => $this->encodeList($value, $type),
            \Poagen\Runtime\Contract\PropertyType::KIND_MAP => $this->encodeMap($value, $type),
            default => $value,
        };
    }

    private function encodeDateTime(mixed $value, \Poagen\Runtime\Contract\PropertyType $type): string
    {
        assert($value instanceof DateTimeInterface);
        $format = $type->format === 'date' ? 'Y-m-d' : DateTimeInterface::RFC3339;

        return $value->format($format);
    }

    private function encodeEnum(mixed $value): string|int
    {
        assert($value instanceof BackedEnum);

        return $value->value;
    }

    private function encodeModel(mixed $value): mixed
    {
        if (!is_object($value)) {
            return $value;
        }

        if ($value instanceof \Poagen\Runtime\ScalarBinding) {
            return $value->toScalar();
        }

        return $this->marshal($value);
    }

    private function encodeScalarBinding(mixed $value): string|int|float|bool
    {
        assert($value instanceof \Poagen\Runtime\ScalarBinding);

        return $value->toScalar();
    }

    /**
     * @return list<mixed>
     */
    private function encodeList(mixed $value, \Poagen\Runtime\Contract\PropertyType $type): array
    {
        assert(is_array($value));
        $itemType = $type->itemType ?? \Poagen\Runtime\Contract\PropertyType::passthrough();
        $out = [];

        foreach ($value as $item) {
            $out[] = $this->encode($item, $itemType);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeMap(mixed $value, \Poagen\Runtime\Contract\PropertyType $type): array
    {
        assert(is_array($value));
        $valueType = $type->itemType ?? \Poagen\Runtime\Contract\PropertyType::passthrough();
        $out = [];

        foreach ($value as $key => $item) {
            $out[(string) $key] = $this->encode($item, $valueType);
        }

        return $out;
    }
}
