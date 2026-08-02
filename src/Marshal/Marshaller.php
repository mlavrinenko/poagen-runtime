<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Marshal;

use BackedEnum;
use DateTimeInterface;
use Mlavrinenko\Paogen\Runtime\Contract\DescriptorParserInterface;
use Mlavrinenko\Paogen\Runtime\Contract\MarshallerInterface;
use Mlavrinenko\Paogen\Runtime\Contract\PropertyType;
use Mlavrinenko\Paogen\Runtime\ScalarBinding;
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
final readonly class Marshaller implements MarshallerInterface
{
    public function __construct(
        private DescriptorParserInterface $descriptors = new DescriptorParser(),
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
            // nested under a name of their own.
            if ($descriptor->additional) {
                $extras = $this->encodeMap($value, $descriptor->type);
                continue;
            }

            $out[$descriptor->wireName] = $this->encode($value, $descriptor->type);
        }

        // `+` and not a merge: a declared property owns its wire name, so an
        // extra key that somehow carries the same one cannot overwrite it.
        return $out + $extras;
    }

    private function encode(mixed $value, PropertyType $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type->kind) {
            PropertyType::KIND_PASSTHROUGH => $value,
            PropertyType::KIND_DATETIME => $this->encodeDateTime($value, $type),
            PropertyType::KIND_ENUM => $this->encodeEnum($value),
            PropertyType::KIND_MODEL, PropertyType::KIND_UNION => $this->encodeModel($value),
            PropertyType::KIND_SCALAR_BINDING => $this->encodeScalarBinding($value),
            PropertyType::KIND_LIST => $this->encodeList($value, $type),
            PropertyType::KIND_MAP => $this->encodeMap($value, $type),
            default => $value,
        };
    }

    private function encodeDateTime(mixed $value, PropertyType $type): string
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

        if ($value instanceof ScalarBinding) {
            return $value->toScalar();
        }

        return $this->marshal($value);
    }

    private function encodeScalarBinding(mixed $value): string|int|float|bool
    {
        assert($value instanceof ScalarBinding);

        return $value->toScalar();
    }

    /**
     * @return list<mixed>
     */
    private function encodeList(mixed $value, PropertyType $type): array
    {
        assert(is_array($value));
        $itemType = $type->itemType ?? PropertyType::passthrough();
        $out = [];

        foreach ($value as $item) {
            $out[] = $this->encode($item, $itemType);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeMap(mixed $value, PropertyType $type): array
    {
        assert(is_array($value));
        $valueType = $type->itemType ?? PropertyType::passthrough();
        $out = [];

        foreach ($value as $key => $item) {
            $out[(string) $key] = $this->encode($item, $valueType);
        }

        return $out;
    }
}
