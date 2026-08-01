<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

/**
 * The shape an operation was promised back, named by the emitted method and
 * honoured by the {@see TransportInterface}.
 *
 * A schema yields five of these and no more: nothing at all, one Model, a
 * list of Models, a scalar, and a map of values the document declared
 * through `additionalProperties`. Carrying the shape as a value rather than
 * as five transport methods keeps the contract at one method to implement,
 * and keeps the emitted return type honest — the template parameter is what
 * static analysis reads to agree that `send()` gives back a `Pet` and not a
 * `mixed`.
 *
 * @template-covariant T
 */
final readonly class ResponseType
{
    /** No body is read; the operation returns `void`. */
    public const string KIND_NOTHING = 'nothing';

    /** One Model, hydrated from the decoded body. */
    public const string KIND_OBJECT = 'object';

    /** A list of Models, each hydrated. */
    public const string KIND_LIST = 'list';

    /** A scalar, cast from the raw body without decoding. */
    public const string KIND_SCALAR = 'scalar';

    /** A map of values, decoded and returned as-is. */
    public const string KIND_MAP = 'map';

    /** A value the document left unconstrained: decoded and returned as-is. */
    public const string KIND_MIXED = 'mixed';

    /**
     * @param class-string|null $model  set for KIND_OBJECT and KIND_LIST
     * @param string            $scalar the PHP type for KIND_SCALAR: `string`, `int`, `float` or `bool`
     * @param self<mixed>|null  $value  the value shape for KIND_MAP
     */
    private function __construct(
        public string $kind,
        public ?string $model = null,
        public string $scalar = '',
        public ?self $value = null,
    ) {}

    /**
     * @return self<null>
     */
    public static function nothing(): self
    {
        /** @var self<null> $type */
        $type = new self(self::KIND_NOTHING);

        return $type;
    }

    /**
     * @template TModel of object
     *
     * @param class-string<TModel> $model
     *
     * @return self<TModel>
     */
    public static function object(string $model): self
    {
        /** @var self<TModel> $type */
        $type = new self(self::KIND_OBJECT, model: $model);

        return $type;
    }

    /**
     * @template TModel of object
     *
     * @param class-string<TModel> $model
     *
     * @return self<list<TModel>>
     */
    public static function listOf(string $model): self
    {
        /** @var self<list<TModel>> $type */
        $type = new self(self::KIND_LIST, model: $model);

        return $type;
    }

    /**
     * @return self<string>
     */
    public static function string(): self
    {
        /** @var self<string> $type */
        $type = new self(self::KIND_SCALAR, scalar: 'string');

        return $type;
    }

    /**
     * @return self<int>
     */
    public static function int(): self
    {
        /** @var self<int> $type */
        $type = new self(self::KIND_SCALAR, scalar: 'int');

        return $type;
    }

    /**
     * @return self<float>
     */
    public static function float(): self
    {
        /** @var self<float> $type */
        $type = new self(self::KIND_SCALAR, scalar: 'float');

        return $type;
    }

    /**
     * @return self<bool>
     */
    public static function bool(): self
    {
        /** @var self<bool> $type */
        $type = new self(self::KIND_SCALAR, scalar: 'bool');

        return $type;
    }

    /**
     * A value the document left unconstrained.
     *
     * @return self<mixed>
     */
    public static function mixed(): self
    {
        /** @var self<mixed> $type */
        $type = new self(self::KIND_MIXED);

        return $type;
    }

    /**
     * A map keyed by string, whose values have the given shape.
     *
     * @template TValue
     *
     * @param self<TValue> $value
     *
     * @return self<array<string, TValue>>
     */
    public static function mapOf(self $value): self
    {
        /** @var self<array<string, TValue>> $type */
        $type = new self(self::KIND_MAP, value: $value);

        return $type;
    }
}
