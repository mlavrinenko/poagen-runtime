<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Http;

use DateTimeImmutable;
use Mlavrinenko\Paogen\Runtime\Contract\HydratorInterface;
use Mlavrinenko\Paogen\Runtime\Contract\PropertyDescriptor;
use Mlavrinenko\Paogen\Runtime\Contract\PropertyType;
use Mlavrinenko\Paogen\Runtime\Contract\ResponseType;
use Mlavrinenko\Paogen\Runtime\Marshal\UnionBranchPicker;
use Mlavrinenko\Paogen\Runtime\ScalarBinding;
use Psr\Http\Message\ResponseInterface;

use function assert;
use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Turns a successful response into the value the emitted method promised.
 *
 * Six shapes and no more, mirroring {@see ResponseType}: nothing, one
 * Model, a list of Models, a scalar read straight off the body, a decoded
 * map, and a binary stream handed back exactly as the transport produced it.
 * A schema produces one of these for every operation, which is why an
 * emitted method can name its return shape in one expression.
 *
 * @internal
 */
final readonly class ResponseDecoder
{
    public function __construct(private HydratorInterface $hydrator) {}

    /**
     * @template T
     *
     * @param ResponseType<T> $expected
     *
     * @return T
     */
    public function decode(ResponseInterface $response, ResponseType $expected): mixed
    {
        return match ($expected->kind) {
            ResponseType::KIND_NOTHING => null,
            ResponseType::KIND_SCALAR => $this->scalar($response, $expected->scalar),
            // The stream is the value — handing it back is the whole
            // decode, and calling getBody() again never reads it, unlike
            // decodeBody()'s (string) cast every other kind runs through.
            ResponseType::KIND_STREAM => $response->getBody(),
            default => $this->value($this->decodeBody($response), $expected),
        };
    }

    /**
     * One decoded value, shaped as the type says. Recursive so a map or a
     * list carries its own value shape rather than a second method per
     * combination.
     *
     * @param ResponseType<mixed> $expected
     */
    private function value(mixed $data, ResponseType $expected): mixed
    {
        return match ($expected->kind) {
            ResponseType::KIND_OBJECT => $this->model($data, $expected),
            ResponseType::KIND_LIST => $this->listOf($data, $expected),
            ResponseType::KIND_SCALAR_LIST => $this->scalarList($data, $expected),
            ResponseType::KIND_MAP => $this->map($data, $expected),
            ResponseType::KIND_DATETIME => $this->dateTime($data),
            ResponseType::KIND_SCALAR_BINDING => $this->scalarBinding($data, $expected),
            ResponseType::KIND_UNION => $this->union($data, $expected),
            // KIND_MIXED: the document left the shape unconstrained, so the
            // decoded body is the answer.
            default => $data,
        };
    }

    /**
     * Exactly one branch, or an exception — {@see UnionBranchPicker} holds
     * the rule, the same one a union-typed property's `Hydrator::decodeUnion`
     * applies. A response has no enclosing payload for a tag to sit on, so
     * `$parent` is always empty; every `Discriminator` this class ever
     * builds carries `onParent: false` for exactly that reason, and
     * `UnionBranchPicker` never reads `$parent` when it does.
     *
     * @param ResponseType<mixed> $expected
     */
    private function union(mixed $data, ResponseType $expected): object
    {
        assert(is_array($data));
        $owner = new PropertyDescriptor(
            phpName: '',
            wireName: $expected->label,
            type: PropertyType::passthrough(),
            discriminator: $expected->discriminator,
            selector: $expected->selector,
        );
        $branch = UnionBranchPicker::pick($data, [], $owner);

        return $this->hydrator->hydrate($branch, $data);
    }

    /**
     * A list of scalars: decoded, then each item shaped by the item type.
     *
     * Nothing is hydrated, because there is no class to hydrate into — the
     * document declared `items: {type: string}`, and a string is already the
     * value the caller was promised.
     *
     * @param ResponseType<mixed> $expected
     *
     * @return list<mixed>
     */
    private function scalarList(mixed $data, ResponseType $expected): array
    {
        assert(is_array($data));
        $itemType = $expected->value;
        $out = [];

        foreach ($data as $item) {
            $out[] = $itemType instanceof ResponseType ? $this->value($item, $itemType) : $item;
        }

        return $out;
    }

    /**
     * A date or date-time string. The same one-line conversion the Hydrator
     * applies to a model property carrying the same `format`, so a `date` in
     * a response list and a `date` on a property cannot drift apart.
     */
    private function dateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        assert(is_string($value));

        return new DateTimeImmutable($value);
    }

    /**
     * @param ResponseType<mixed> $expected
     */
    private function scalarBinding(mixed $value, ResponseType $expected): ScalarBinding
    {
        /** @var class-string<ScalarBinding> $class */
        $class = $expected->model;

        if ($value instanceof $class) {
            return $value;
        }

        return $class::fromScalar($value);
    }

    private function scalar(ResponseInterface $response, string $type): string|int|float|bool
    {
        $body = (string) $response->getBody();

        return match ($type) {
            'int' => (int) $body,
            'float' => (float) $body,
            'bool' => (bool) $body,
            default => $body,
        };
    }

    /**
     * @param ResponseType<mixed> $expected
     */
    private function model(mixed $data, ResponseType $expected): object
    {
        $class = $expected->model;
        assert($class !== null, 'an object response type carries its model class');

        return $this->hydrator->hydrate($class, (array) $data);
    }

    /**
     * @param ResponseType<mixed> $expected
     *
     * @return list<object>
     */
    private function listOf(mixed $data, ResponseType $expected): array
    {
        assert(is_array($data));
        $out = [];

        foreach ($data as $item) {
            $out[] = $this->model($item, $expected);
        }

        return $out;
    }

    /**
     * A map's values carry a shape of their own: scalars and unconstrained
     * values pass through, and a declared Model is hydrated like any other.
     *
     * @param ResponseType<mixed> $expected
     *
     * @return array<string, mixed>
     */
    private function map(mixed $data, ResponseType $expected): array
    {
        assert(is_array($data));
        $valueType = $expected->value;
        $out = [];

        foreach ($data as $key => $item) {
            $out[(string) $key] = $valueType instanceof ResponseType ? $this->value($item, $valueType) : $item;
        }

        return $out;
    }

    private function decodeBody(ResponseInterface $response): mixed
    {
        /** @var mixed $decoded */
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
