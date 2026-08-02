<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

/**
 * One value-shape descriptor used by the {@see Marshaller} and {@see Hydrator}
 * to (de)serialise a single Model property. A flat tagged union — `kind` plus
 * the optional fields that kind needs — keeps reflection output and runtime
 * dispatch in a single small object per property. See
 * `attribute-and-phpdoc-driven-serialization`.
 */
final readonly class PropertyType
{
    public const string KIND_PASSTHROUGH = 'passthrough';
    public const string KIND_DATETIME = 'datetime';
    public const string KIND_ENUM = 'enum';
    public const string KIND_MODEL = 'model';
    public const string KIND_SCALAR_BINDING = 'scalar_binding';
    public const string KIND_UNION = 'union';
    public const string KIND_LIST = 'list';
    public const string KIND_MAP = 'map';

    /**
     * @param class-string|null    $class    enum FQCN, model FQCN, or VO FQCN depending on kind
     * @param list<class-string>   $branches non-empty when kind === KIND_UNION
     */
    public function __construct(
        public string $kind,
        public ?string $class = null,
        public array $branches = [],
        public ?self $itemType = null,
        public ?string $format = null,
    ) {}

    public static function passthrough(): self
    {
        return new self(self::KIND_PASSTHROUGH);
    }

    public static function dateTime(string $format = 'date-time'): self
    {
        return new self(self::KIND_DATETIME, format: $format);
    }

    /**
     * @param class-string $enum
     */
    public static function enum(string $enum): self
    {
        return new self(self::KIND_ENUM, class: $enum);
    }

    /**
     * @param class-string $model
     */
    public static function model(string $model): self
    {
        return new self(self::KIND_MODEL, class: $model);
    }

    /**
     * @param class-string $vo
     */
    public static function scalarBinding(string $vo): self
    {
        return new self(self::KIND_SCALAR_BINDING, class: $vo);
    }

    /**
     * @param list<class-string> $branches
     */
    public static function union(array $branches): self
    {
        return new self(self::KIND_UNION, branches: $branches);
    }

    public static function listOf(self $itemType): self
    {
        return new self(self::KIND_LIST, itemType: $itemType);
    }

    public static function mapOf(self $valueType): self
    {
        return new self(self::KIND_MAP, itemType: $valueType);
    }
}
