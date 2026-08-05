<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Contract\Endpoint;
use Poagen\Runtime\Contract\ResponseType;
use Poagen\Runtime\Http\HttpTransport;
use Poagen\Runtime\Tests\Fixtures\ApiException;
use Poagen\Runtime\Tests\Fixtures\RecordingClient;
use Psr\Http\Message\RequestInterface;

use function preg_replace;
use function trim;

/**
 * Every body shape a schema declares, encoded once in the runtime.
 *
 * The marshaller runs first for every kind that has a Model behind it, so
 * wire names and formats are decided once and the content types cannot
 * disagree about a value. An absent optional field is dropped rather than
 * sent empty, which is what lets an emitted method pass its parameters
 * without an `if`.
 */
final class TransportRequestBodyKindsTest extends TestCase
{
    public function testNoBodyMeansNoContentTypeAtAll(): void
    {
        $request = $this->send(Endpoint::get('/pet/1'));

        self::assertFalse($request->hasHeader('Content-Type'));
        self::assertSame('', (string) $request->getBody());
    }

    public function testAJsonBodyIsMarshalledThenEncoded(): void
    {
        $request = $this->send(Endpoint::post('/contact')->json(new Fixtures\Contact('Grace')));

        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        // `display-name`, not `displayName`: the marshaller decided the wire
        // name before the encoder ever saw the value.
        self::assertSame('{"display-name":"Grace","email":null}', (string) $request->getBody());
    }

    public function testAJsonListMarshallsEveryItem(): void
    {
        $request = $this->send(
            Endpoint::post('/contacts')->jsonList([new Fixtures\Contact('Grace'), new Fixtures\Contact('Ada')]),
        );

        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(
            '[{"display-name":"Grace","email":null},{"display-name":"Ada","email":null}]',
            (string) $request->getBody(),
        );
    }

    public function testAnUnconstrainedObjectBodyIsSentAsTheCallerHoldsIt(): void
    {
        $request = $this->send(Endpoint::post('/opaque')->jsonRaw(['a' => 1, 'b' => null]));

        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"a":1,"b":null}', (string) $request->getBody());
    }

    public function testAnXmlBodyIsMarshalledThenRenderedUnderTheNamedRoot(): void
    {
        $request = $this->send(Endpoint::post('/contact')->xml(new Fixtures\Contact('Grace'), 'contact'));

        self::assertSame('application/xml', $request->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<contact>', (string) $request->getBody());
        self::assertStringContainsString('<display-name>Grace</display-name>', (string) $request->getBody());
    }

    public function testAnXmlListRepeatsTheNamedItemElement(): void
    {
        $request = $this->send(
            Endpoint::post('/contacts')->xmlList([new Fixtures\Contact('Grace'), new Fixtures\Contact('Ada')], 'contacts', 'contact'),
        );
        $body = (string) $request->getBody();

        self::assertSame('application/xml', $request->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<contacts>', $body);
        self::assertSame(2, substr_count($body, '<contact>'));
    }

    /**
     * thread-the-validated-nested-container-map-into-the-generated-client:
     * `Endpoint::xmlList()`'s `$nestedContainers` is enough on its own — no
     * `XmlWriter` constructed by hand, no substitute transport — to wrap
     * `tags` in one `<tags>` container instead of OpenAPI's unwrapped
     * default of repeating it as a sibling per entry.
     */
    public function testANestedContainerBindingWrapsThePropertyOnThePlainDefaultTransport(): void
    {
        $pet = new Fixtures\Pet(name: 'Rex', tags: [new Fixtures\Tag(id: 1, name: 'a'), new Fixtures\Tag(id: 2, name: 'b')]);
        $request = $this->send(
            Endpoint::post('/pets')->xmlList([$pet], 'pets', 'pet', ['tags' => 'tag']),
        );
        $body = $this->collapsed((string) $request->getBody());

        self::assertSame(1, substr_count($body, '<tags>'));
        self::assertSame(2, substr_count($body, '<tag>'));
        self::assertStringContainsString(
            '<tags><tag><id>1</id><name>a</name></tag><tag><id>2</id><name>b</name></tag></tags>',
            $body,
        );
    }

    /**
     * The same payload with no binding: AC3 of the same task — a body with
     * no nested container renders exactly as it always has, the `tags`
     * property repeated as a sibling per entry (OpenAPI's unwrapped
     * default) rather than wrapped in a container.
     */
    public function testWithNoNestedContainerBindingTheSamePropertyStaysUnwrapped(): void
    {
        $pet = new Fixtures\Pet(name: 'Rex', tags: [new Fixtures\Tag(id: 1, name: 'a'), new Fixtures\Tag(id: 2, name: 'b')]);
        $request = $this->send(Endpoint::post('/pets')->xmlList([$pet], 'pets', 'pet'));
        $body = $this->collapsed((string) $request->getBody());

        self::assertSame(2, substr_count($body, '<tags>'));
        self::assertStringNotContainsString('<tag>', $body);
        self::assertStringContainsString('<tags><id>1</id><name>a</name></tags>', $body);
        self::assertStringContainsString('<tags><id>2</id><name>b</name></tags>', $body);
    }

    public function testAFormBodyIsRfc1738EncodedAndDropsTheFieldsTheCallerLeftOut(): void
    {
        $request = $this->send(
            Endpoint::post('/token')->form(['grantType' => 'password', 'clientId' => 'my app', 'scope' => null]),
        );

        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        // `+` for the space is RFC1738, which is what an OAuth2 token
        // endpoint expects.
        self::assertSame('grantType=password&clientId=my+app', (string) $request->getBody());
    }

    public function testATextBodyIsSentVerbatimUnderTheDeclaredContentType(): void
    {
        $request = $this->send(Endpoint::post('/markdown/raw')->text('Hello **world**', 'text/plain'));

        // No encoding step at all: the document said the body is a string,
        // so the string the caller passed is the body.
        self::assertSame('text/plain', $request->getHeaderLine('Content-Type'));
        self::assertSame('Hello **world**', (string) $request->getBody());
    }

    public function testABinaryBodyReachesTheRequestAsTheSameStreamNeverReadWhole(): void
    {
        $stream = new Fixtures\NoWholeReadStream(new Psr17Factory()->createStream('bytes'));
        $request = $this->send(Endpoint::post('/assets')->binary($stream, 'application/octet-stream'));

        self::assertSame('application/octet-stream', $request->getHeaderLine('Content-Type'));
        // Identity, not equality: a `createStream((string) $stream)` would
        // have produced a different object, and would have thrown getting
        // there. An upload therefore costs the process no more memory than
        // the client's own chunk size, whatever the file's size is.
        self::assertSame($stream, $request->getBody());
        self::assertSame(0, $request->getBody()->tell());
    }

    /**
     * The XML prolog and inter-element whitespace stripped, so a wrapped or
     * unwrapped shape can be asserted on as one substring rather than
     * fighting `DOMDocument::formatOutput`'s indentation.
     */
    private function collapsed(string $xml): string
    {
        $withoutProlog = (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $xml);

        return (string) preg_replace('/>\s+</', '><', trim($withoutProlog));
    }

    private function send(Endpoint $endpoint): RequestInterface
    {
        $factory = new Psr17Factory();
        $http = new RecordingClient(new Response(204, [], ''));

        new HttpTransport(
            'https://example.test/api',
            ApiException::class,
            $http,
            $factory,
            $factory,
        )->send($endpoint, ResponseType::nothing());

        self::assertNotNull($http->lastRequest);

        return $http->lastRequest;
    }
}
