<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Http;

use Mlavrinenko\Paogen\Runtime\Contract\HydratorInterface;
use Mlavrinenko\Paogen\Runtime\Contract\ResponseType;
use Psr\Http\Message\ResponseInterface;

use function assert;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Turns a successful response into the value the emitted method promised.
 *
 * Five shapes and no more, mirroring {@see ResponseType}: nothing, one
 * Model, a list of Models, a scalar read straight off the body, and a
 * decoded map. A schema produces one of these for every operation, which is
 * why an emitted method can name its return shape in one expression.
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
            ResponseType::KIND_MAP => $this->map($data, $expected),
            // KIND_MIXED: the document left the shape unconstrained, so the
            // decoded body is the answer.
            default => $data,
        };
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
