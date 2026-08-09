<?php

declare(strict_types=1);

namespace Poagen\Runtime\Contract;

use DateTimeImmutable;
use Poagen\Runtime\Attribute\BranchSelector;
use Poagen\Runtime\Attribute\Discriminator;
use Poagen\Runtime\Codec;
use Poagen\Runtime\ScalarBinding;
use Psr\Http\Message\StreamInterface;

/**
 * The shape an operation was promised back, named by the emitted method and
 * honoured by the {@see TransportInterface}.
 *
 * A method's own `send()` call names exactly one kind at the top level now:
 * KIND_ENVELOPE, carrying the per-status resolution as a
 * {@see StatusResponseMap}. Every other kind names one status's body inside
 * that map, or nests the way a map's value already could — the template
 * parameter is what static analysis reads to agree `send()` gives back an
 * `Envelope<Pet>`, not `mixed`.
 *
 * @template-covariant T
 */
final readonly class ResponseType implements ResponseTypeKind
{
    /**
     * @param class-string|null $model  set for KIND_OBJECT and KIND_LIST
     * @param string            $scalar the PHP type for KIND_SCALAR: `string`, `int`, `float` or `bool`
     * @param self<mixed>|null  $value  the value shape for KIND_MAP
     * @param Discriminator|null $discriminator set for KIND_UNION when the branch is picked by a tag
     * @param BranchSelector|null $selector set for KIND_UNION when the branch is picked structurally
     * @param string             $label         set for KIND_UNION — names the union in a picking failure's message
     * @param StatusResponseMap|null $statuses set for KIND_ENVELOPE
     */
    private function __construct(
        public string $kind,
        public ?string $model = null,
        public string $scalar = '',
        public ?self $value = null,
        public ?Discriminator $discriminator = null,
        public ?BranchSelector $selector = null,
        public string $label = '',
        public ?StatusResponseMap $statuses = null,
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
     * A binary body, handed back exactly as the transport produced it.
     *
     * @return self<StreamInterface>
     */
    public static function stream(): self
    {
        /** @var self<StreamInterface> $type */
        $type = new self(self::KIND_STREAM);

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
     * @template TVo of \Poagen\Runtime\ScalarBinding
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

    /**
     * A scalar converted by a registered {@see Codec} —
     * the foreign-type sibling of {@see binding()}. `$codec` is the
     * converter, not the type it targets: a codec-bound response scalar is
     * whatever {@see Codec::targetType()} says. Unlike
     * {@see binding()}, that answer is not carried in `self`'s own template
     * parameter — `ScalarBinding::fromScalar()` returns `self`, so PHPStan
     * infers the bound type from the class alone, but a codec converts on
     * behalf of a type it does not declare itself, so nothing here can name
     * it statically. A caller that needs the concrete type back narrows it
     * itself, the same way `mixed` from any other dynamic decode does.
     *
     * @param class-string<Codec> $codec
     *
     * @return self<mixed>
     */
    public static function codec(string $codec): self
    {
        /** @var self<mixed> $type */
        $type = new self(self::KIND_CODEC, model: $codec);

        return $type;
    }

    /**
     * A response body — or a list item, nested inside {@see scalarListOf()} —
     * that is one of several Models, picked exactly the way a union-typed
     * property already is: a tag, or a field only one branch declares.
     * Exactly one of `$discriminator`/`$selector` is set, matching the
     * generator's own invariant that a union it resolved always carries one.
     *
     * @return self<object>
     */
    public static function union(?Discriminator $discriminator, ?BranchSelector $selector, string $label): self
    {
        /** @var self<object> $type */
        $type = new self(self::KIND_UNION, discriminator: $discriminator, selector: $selector, label: $label);

        return $type;
    }

    /**
     * Every response an operation's document declared, as one value.
     *
     * @return self<Envelope<mixed>>
     */
    public static function envelope(StatusResponseMap $statuses): self
    {
        /** @var self<Envelope<mixed>> $type */
        $type = new self(self::KIND_ENVELOPE, statuses: $statuses);

        return $type;
    }
}
