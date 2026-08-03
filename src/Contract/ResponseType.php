<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

use DateTimeImmutable;
use Mlavrinenko\Paogen\Runtime\ScalarBinding;

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

    /**
     * A list of scalars, decoded and converted item by item. No model class
     * is involved, which is what separates it from KIND_LIST.
     */
    public const string KIND_SCALAR_LIST = 'scalar_list';

    /** A date or date-time string, as a `\DateTimeImmutable`. */
    public const string KIND_DATETIME = 'datetime';

    /** A scalar wrapped by a `ScalarBinding` value object. */
    public const string KIND_SCALAR_BINDING = 'scalar_binding';

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

    /**
     * A list of scalars, each shaped by `$item`.
     *
     * The list kinds are deliberately two rather than one: `listOf()` names a
     * model class and hydrates, and there is no class to name when the
     * document declared `items: {type: string}`. Sharing one kind would have
     * meant a nullable model on every list and a runtime branch on it.
     *
     * @template TItem
     *
     * @param self<TItem> $item
     *
     * @return self<list<TItem>>
     */
    public static function scalarListOf(self $item): self
    {
        /** @var self<list<TItem>> $type */
        $type = new self(self::KIND_SCALAR_LIST, value: $item);

        return $type;
    }

    /**
     * A date or date-time string. Mirrors what a model property annotated
     * with the same `format` already hydrates to, so the two cannot disagree.
     *
     * @return self<DateTimeImmutable>
     */
    public static function dateTime(): self
    {
        /** @var self<DateTimeImmutable> $type */
        $type = new self(self::KIND_DATETIME);

        return $type;
    }

    /**
     * A scalar the consumer bound to a value object of their own via
     * `#[BindsFormat]` + `ScalarBinding`.
     *
     * @template TVo of ScalarBinding
     *
     * @param class-string<TVo> $vo
     *
     * @return self<TVo>
     */
    public static function binding(string $vo): self
    {
        /** @var self<TVo> $type */
        $type = new self(self::KIND_SCALAR_BINDING, model: $vo);

        return $type;
    }
}
