<?php

declare(strict_types=1);

namespace Poagen\Runtime\Contract;

use BackedEnum;
use Psr\Http\Message\StreamInterface;
use Stringable;

use function implode;
use function is_array;
use function str_replace;
use function urlencode;

/**
 * One request, described rather than built.
 *
 * An emitted operation names a verb and a path template and then says what
 * the document declared: which path tokens to fill, which query keys and
 * headers to send, and what the body is. It never assembles a URI or encodes
 * a payload itself — a {@see TransportInterface} does that, which is why the
 * same four lines can express every operation in a schema.
 *
 * The builder is deliberately null-tolerant: an optional parameter that was
 * not supplied is passed anyway and dropped here, so an emitted method has
 * no `if` in it. A list value is expanded into repeated keys
 * (`tags=a&tags=b`), which is what OpenAPI's `explode: true` means and what
 * `http_build_query` would get wrong.
 *
 * {@see uri()} is the single place the wire form of a path and query string
 * is decided, so every transport — the default one and any substitute —
 * agrees on it.
 */
final class Endpoint
{
    /** @var array<string, string> */
    private array $pathParams = [];

    /** @var list<array{string, string}> */
    private array $query = [];

    /** @var array<string, string> */
    private array $headers = [];

    private ?EndpointBody $body = null;

    private function __construct(
        private readonly string $method,
        private readonly string $pathTemplate,
    ) {}

    public static function get(string $path): self
    {
        return new self('GET', $path);
    }

    public static function post(string $path): self
    {
        return new self('POST', $path);
    }

    public static function put(string $path): self
    {
        return new self('PUT', $path);
    }

    public static function patch(string $path): self
    {
        return new self('PATCH', $path);
    }

    public static function delete(string $path): self
    {
        return new self('DELETE', $path);
    }

    public static function head(string $path): self
    {
        return new self('HEAD', $path);
    }

    public static function options(string $path): self
    {
        return new self('OPTIONS', $path);
    }

    public static function trace(string $path): self
    {
        return new self('TRACE', $path);
    }

    /**
     * Any other verb, for a document that declares one.
     */
    public static function method(string $httpMethod, string $path): self
    {
        return new self($httpMethod, $path);
    }

    /**
     * Fill a `{token}` in the path template. Path parameters are required by
     * the specification, so there is no null case.
     */
    public function path(string $name, string|int|float|bool|BackedEnum|Stringable $value): self
    {
        $this->pathParams[$name] = $this->stringify($value);

        return $this;
    }

    /**
     * Add a query key. `null` is dropped (an optional parameter the caller
     * left out), and a list becomes one repeated key per item.
     *
     * @param string|int|float|bool|BackedEnum|Stringable|list<string|int|float|bool|BackedEnum|Stringable>|null $value
     */
    public function query(string $name, string|int|float|bool|BackedEnum|Stringable|array|null $value): self
    {
        if ($value === null) {
            return $this;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->query[] = [$name, $this->stringify($item)];
            }

            return $this;
        }

        $this->query[] = [$name, $this->stringify($value)];

        return $this;
    }

    /**
     * Add a header. `null` is dropped; a repeated name replaces the earlier
     * value, matching PSR-7's `withHeader`.
     */
    public function header(string $name, string|int|float|bool|BackedEnum|Stringable|null $value): self
    {
        if ($value === null) {
            return $this;
        }

        $this->headers[$name] = $this->stringify($value);

        return $this;
    }

    /**
     * A Model sent as JSON. The transport marshals it.
     */
    public function json(object $model): self
    {
        return $this->withBody(new EndpointBody(EndpointBody::KIND_JSON, $model));
    }

    /**
     * A list of Models sent as a JSON array.
     *
     * @param list<object> $models
     */
    public function jsonList(array $models): self
    {
        return $this->withBody(new EndpointBody(EndpointBody::KIND_JSON_LIST, $models));
    }

    /**
     * An already-wire-shaped payload sent as JSON — the schema declared an
     * object with no properties, so there is no Model to marshal.
     *
     * @param array<string, mixed> $data
     */
    public function jsonRaw(array $data): self
    {
        return $this->withBody(new EndpointBody(EndpointBody::KIND_JSON_RAW, $data));
    }

    /**
     * A Model sent as XML under `$rootElement`.
     */
    public function xml(object $model, string $rootElement): self
    {
        return $this->withBody(new EndpointBody(EndpointBody::KIND_XML, $model, $rootElement));
    }

    /**
     * A list of Models sent as XML: `$itemElement` repeated inside
     * `$rootElement`. `$nestedContainers` is `Profile::$xmlElements`'s
     * validated binding, passed straight through to whichever writer renders.
     *
     * @param list<object>         $models
     * @param array<string,string> $nestedContainers
     */
    public function xmlList(array $models, string $rootElement, string $itemElement, array $nestedContainers = []): self
    {
        return $this->withBody(new EndpointBody(EndpointBody::KIND_XML_LIST, $models, $rootElement, $itemElement, nestedContainers: $nestedContainers));
    }

    /**
     * Declared fields sent as `application/x-www-form-urlencoded`. Null
     * entries are dropped, so an optional field needs no `if` at the call
     * site.
     *
     * @param array<string, string|int|float|bool|null> $fields
     */
    public function form(array $fields): self
    {
        return $this->withBody(new EndpointBody(EndpointBody::KIND_FORM, $fields));
    }

    /**
     * Text sent verbatim under `$contentType`.
     */
    public function text(string $text, string $contentType): self
    {
        return $this->withBody(new EndpointBody(EndpointBody::KIND_TEXT, $text, mediaType: $contentType));
    }

    /**
     * Bytes, as the stream the caller opened. The stream travels to the
     * request unread, so an upload is never materialised as a PHP string.
     */
    public function binary(StreamInterface $stream, string $contentType): self
    {
        return $this->withBody(new EndpointBody(EndpointBody::KIND_BINARY, $stream, mediaType: $contentType));
    }

    public function httpMethod(): string
    {
        return $this->method;
    }

    /**
     * The path template with its tokens unfilled — the route, for a
     * transport that groups metrics or traces by endpoint rather than by
     * concrete URI.
     */
    public function route(): string
    {
        return $this->pathTemplate;
    }

    /**
     * The request target relative to the base address: path tokens filled,
     * query string appended if there is one. Exactly one `?` appears, and
     * every value is urlencoded.
     */
    public function uri(): string
    {
        $path = $this->pathTemplate;

        foreach ($this->pathParams as $name => $value) {
            $path = str_replace('{' . $name . '}', $value, $path);
        }

        if ($this->query === []) {
            return $path;
        }

        $pairs = [];

        foreach ($this->query as [$name, $value]) {
            $pairs[] = $name . '=' . urlencode($value);
        }

        return $path . '?' . implode('&', $pairs);
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): ?EndpointBody
    {
        return $this->body;
    }

    private function withBody(EndpointBody $body): self
    {
        $this->body = $body;

        return $this;
    }

    private function stringify(string|int|float|bool|BackedEnum|Stringable $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
