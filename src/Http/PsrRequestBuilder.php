<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Http;

use Mlavrinenko\Paogen\Runtime\Contract\Endpoint;
use Mlavrinenko\Paogen\Runtime\Contract\EndpointBody;
use Mlavrinenko\Paogen\Runtime\Contract\MarshallerInterface;
use Mlavrinenko\Paogen\Runtime\Contract\XmlWriterInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

use function http_build_query;
use function is_array;
use function is_object;
use function is_string;
use function json_encode;
use function rtrim;

use const JSON_THROW_ON_ERROR;
use const PHP_QUERY_RFC1738;

/**
 * Turns an {@see Endpoint} into a PSR-7 request.
 *
 * The half of {@see HttpTransport} that knows about messages rather than
 * about sending them, kept separate so each stays readable — and so a
 * transport that speaks something other than PSR-18 can still reuse the
 * encoding rules.
 *
 * @internal
 */
final readonly class PsrRequestBuilder
{
    public function __construct(
        private string $baseUri,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private MarshallerInterface $marshaller,
        private XmlWriterInterface $xmlWriter,
    ) {}

    public function build(Endpoint $endpoint): RequestInterface
    {
        $request = $this->requestFactory->createRequest(
            $endpoint->httpMethod(),
            rtrim($this->baseUri, '/') . $endpoint->uri(),
        );

        foreach ($endpoint->headers() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $body = $endpoint->body();

        if (!$body instanceof EndpointBody) {
            return $request;
        }

        return $request
            ->withHeader('Content-Type', $body->contentType())
            ->withBody($this->stream($body));
    }

    /**
     * The body as a stream.
     *
     * A payload that is already a `StreamInterface` — which is what
     * {@see Endpoint::binary()} accepts and nothing else produces — is handed
     * to the request as it stands. Nothing on this path casts it to a string
     * or calls `getContents()`, so a file upload reaches the PSR-18 client as
     * the handle the caller opened and is read in whatever chunks that client
     * chooses. Loading it whole would be a property of paogen rather than of
     * the document, and the document says nothing about the size of a
     * `format: binary` body.
     *
     * Every other kind is encoded to a string first, because JSON, XML and
     * form encoding each need the entire value before they can emit any of
     * it — there is nothing to stream.
     */
    private function stream(EndpointBody $body): StreamInterface
    {
        $payload = $body->payload;

        if ($payload instanceof StreamInterface) {
            return $payload;
        }

        return $this->streamFactory->createStream($this->encode($body));
    }

    private function encode(EndpointBody $body): string
    {
        return match ($body->kind) {
            EndpointBody::KIND_JSON => $this->toJson($this->marshalOne($body)),
            EndpointBody::KIND_JSON_LIST => $this->toJson($this->marshalMany($body)),
            EndpointBody::KIND_XML => $this->xmlWriter->renderObject($this->marshalOne($body), $body->rootElement),
            EndpointBody::KIND_XML_LIST => $this->xmlWriter->renderList(
                $this->marshalMany($body),
                $body->rootElement,
                $body->itemElement,
                $body->nestedContainers,
            ),
            EndpointBody::KIND_FORM => http_build_query($this->fields($body), '', '&', PHP_QUERY_RFC1738),
            // A text body is the wire form already. KIND_BINARY only reaches
            // here when its payload is not a stream, which `Endpoint::binary()`
            // cannot produce — sending the string is still truer than encoding
            // it as JSON.
            EndpointBody::KIND_TEXT, EndpointBody::KIND_BINARY => $this->text($body),
            // KIND_JSON_RAW: the schema declared an object with no properties,
            // so the caller already holds the wire shape.
            default => $this->toJson($this->fields($body)),
        };
    }

    private function text(EndpointBody $body): string
    {
        $payload = $body->payload;

        return is_string($payload) ? $payload : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function marshalOne(EndpointBody $body): array
    {
        $payload = $body->payload;

        return is_object($payload) ? $this->marshaller->marshal($payload) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function marshalMany(EndpointBody $body): array
    {
        $payload = $body->payload;

        if (!is_array($payload)) {
            return [];
        }

        $out = [];

        foreach ($payload as $model) {
            if (is_object($model)) {
                $out[] = $this->marshaller->marshal($model);
            }
        }

        return $out;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function fields(EndpointBody $body): array
    {
        $payload = $body->payload;

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<mixed> $data
     */
    private function toJson(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
