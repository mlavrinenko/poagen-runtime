<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Contract;

/**
 * What an {@see Endpoint} carries as its request body, and how it is meant
 * to reach the wire.
 *
 * The payload is kept as the caller passed it — a Model, a list of Models,
 * or a plain array — because turning it into bytes needs a marshaller and a
 * writer, which are the transport's collaborators and not the endpoint's.
 * A substitute transport is free to encode it differently (a fake may just
 * record the object) as long as it honours {@see contentType()}.
 */
final readonly class EndpointBody
{
    /** A Model, marshalled and sent as a JSON object. */
    public const string KIND_JSON = 'json';

    /** A list of Models, each marshalled, sent as a JSON array. */
    public const string KIND_JSON_LIST = 'json_list';

    /** An array already in wire shape, sent as JSON without marshalling. */
    public const string KIND_JSON_RAW = 'json_raw';

    /** A Model, marshalled and rendered as an XML document. */
    public const string KIND_XML = 'xml';

    /** A list of Models rendered as repeated XML elements. */
    public const string KIND_XML_LIST = 'xml_list';

    /** Declared fields sent as `application/x-www-form-urlencoded`. */
    public const string KIND_FORM = 'form';

    /**
     * @param object|list<object>|array<string, mixed> $payload
     */
    public function __construct(
        public string $kind,
        public object|array $payload,
        public string $rootElement = '',
        public string $itemElement = '',
    ) {}

    public function contentType(): string
    {
        return match ($this->kind) {
            self::KIND_XML, self::KIND_XML_LIST => 'application/xml',
            self::KIND_FORM => 'application/x-www-form-urlencoded',
            default => 'application/json',
        };
    }
}
