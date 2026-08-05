<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Contract\Endpoint;
use Poagen\Runtime\Contract\EndpointBody;

use function substr_count;

/**
 * collapse-operations-onto-a-runtime-transport: the wire form of a path and
 * a query string is decided in one place, so an emitted operation can name
 * its parameters and nothing else.
 *
 * These pin the rules an emitted method now relies on rather than restating:
 * a null drops itself, a list repeats its key, and exactly one `?` appears.
 */
final class EndpointTest extends TestCase
{
    public function testAPathTokenIsFilledAndAQueryStringIsAppendedAfterOneQuestionMark(): void
    {
        $uri = Endpoint::get('/store/{storeId}/items')
            ->path('storeId', 7)
            ->query('limit', 5)
            ->query('cursor', 'abc')
            ->uri();

        self::assertSame('/store/7/items?limit=5&cursor=abc', $uri);
        self::assertSame(1, substr_count($uri, '?'));
    }

    public function testANullQueryKeyOrHeaderDropsItselfSoAnEmittedMethodNeedsNoBranch(): void
    {
        $endpoint = Endpoint::get('/order')
            ->query('limit', null)
            ->query('cursor', 'abc')
            ->header('api_key', null)
            ->header('X-Trace-ID', 'trace-1');

        self::assertSame('/order?cursor=abc', $endpoint->uri());
        self::assertSame(['X-Trace-ID' => 'trace-1'], $endpoint->headers());
    }

    public function testAnOperationWhoseOptionalKeysAreAllAbsentGetsNoQuestionMarkAtAll(): void
    {
        self::assertSame('/order', Endpoint::get('/order')->query('limit', null)->uri());
    }

    public function testAListQueryValueRepeatsTheKeyRatherThanBracketingIndices(): void
    {
        $uri = Endpoint::get('/pet/findByTags')->query('tags', ['a', 'b'])->uri();

        // `explode: true` is repeated keys. `http_build_query` would spell
        // this `tags%5B0%5D=a`, which the vendor does not accept.
        self::assertSame('/pet/findByTags?tags=a&tags=b', $uri);
        self::assertStringNotContainsString('tags%5B', $uri);
    }

    public function testAnEmptyListContributesNoQueryStringAtAll(): void
    {
        self::assertSame('/pet/findByTags', Endpoint::get('/pet/findByTags')->query('tags', [])->uri());
    }

    /**
     * `support-the-parameter-shapes-real-documents-use`: a `oneOf`/`anyOf`
     * of a scalar and a list of one (GitHub's `fields`/`has`) keeps both
     * arms as the native union `string|array|null` rather than normalising
     * to one, per the owner's ruling — so the same `$name` sends a single
     * key for the scalar arm and the repeated-key form for the list arm,
     * and the two differ on the wire for every value except a one-element
     * list. `Endpoint::query()`'s signature already accepts either arm at
     * runtime; this is the proof.
     */
    public function testAScalarOrListParameterSerialisesEachArmDifferently(): void
    {
        $scalarArm = Endpoint::get('/projects/items')->query('fields', 'name')->uri();
        $listArm = Endpoint::get('/projects/items')->query('fields', ['name', 'status'])->uri();

        self::assertSame('/projects/items?fields=name', $scalarArm);
        self::assertSame('/projects/items?fields=name&fields=status', $listArm);
    }

    public function testEveryQueryValueIsUrlencodedSoACredentialCannotSmuggleASecondPair(): void
    {
        self::assertSame(
            '/status?k=a%26b%3Dc',
            Endpoint::get('/status')->query('k', 'a&b=c')->uri(),
        );
    }

    public function testABackedEnumTravelsAsItsValueAndABoolAsPhpSpellsIt(): void
    {
        $uri = Endpoint::get('/pet/findByStatus')
            ->query('status', Fixtures\Status::Placed)
            ->query('archived', false)
            ->query('starred', true)
            ->uri();

        self::assertSame('/pet/findByStatus?status=placed&archived=&starred=1', $uri);
    }

    public function testARepeatedHeaderNameReplacesTheEarlierValueAsPsr7Does(): void
    {
        $endpoint = Endpoint::post('/proxy')
            ->header('X-Trace-ID', 'first')
            ->header('X-Trace-ID', 'second');

        self::assertSame(['X-Trace-ID' => 'second'], $endpoint->headers());
    }

    public function testEachVerbNamesItselfAndAnythingElseCanStillBeSpelledOut(): void
    {
        self::assertSame('GET', Endpoint::get('/x')->httpMethod());
        self::assertSame('POST', Endpoint::post('/x')->httpMethod());
        self::assertSame('PUT', Endpoint::put('/x')->httpMethod());
        self::assertSame('PATCH', Endpoint::patch('/x')->httpMethod());
        self::assertSame('DELETE', Endpoint::delete('/x')->httpMethod());
        self::assertSame('HEAD', Endpoint::head('/x')->httpMethod());
        self::assertSame('OPTIONS', Endpoint::options('/x')->httpMethod());
        self::assertSame('TRACE', Endpoint::trace('/x')->httpMethod());
        self::assertSame('LINK', Endpoint::method('LINK', '/x')->httpMethod());
    }

    public function testEachBodyKindNamesItsContentTypeAndKeepsThePayloadAsGiven(): void
    {
        $contact = new Fixtures\Contact('Grace');

        $kinds = [
            [Endpoint::post('/x')->json($contact), EndpointBody::KIND_JSON, 'application/json'],
            [Endpoint::post('/x')->jsonList([$contact]), EndpointBody::KIND_JSON_LIST, 'application/json'],
            [Endpoint::post('/x')->jsonRaw(['a' => 1]), EndpointBody::KIND_JSON_RAW, 'application/json'],
            [Endpoint::post('/x')->xml($contact, 'contact'), EndpointBody::KIND_XML, 'application/xml'],
            [Endpoint::post('/x')->xmlList([$contact], 'l', 'i'), EndpointBody::KIND_XML_LIST, 'application/xml'],
            [Endpoint::post('/x')->form(['a' => 1]), EndpointBody::KIND_FORM, 'application/x-www-form-urlencoded'],
        ];

        foreach ($kinds as [$endpoint, $kind, $contentType]) {
            $body = $endpoint->body();

            self::assertInstanceOf(EndpointBody::class, $body);
            self::assertSame($kind, $body->kind);
            self::assertSame($contentType, $body->contentType());
        }

        self::assertNull(Endpoint::get('/x')->body());
    }

    public function testTheXmlElementNamesTheDocumentSuppliedTravelWithTheBody(): void
    {
        $body = Endpoint::post('/x')->xmlList([new Fixtures\Contact('Grace')], 'goods', 'good')->body();

        self::assertInstanceOf(EndpointBody::class, $body);
        self::assertSame('goods', $body->rootElement);
        self::assertSame('good', $body->itemElement);
        // No binding named: the map this task threads through defaults to
        // empty, which is what keeps an unconfigured body byte-identical.
        self::assertSame([], $body->nestedContainers);
    }

    /**
     * thread-the-validated-nested-container-map-into-the-generated-client:
     * a nested-container binding travels on the body itself now, rather than
     * a consumer rebuilding the same map for their own `XmlWriter`.
     */
    public function testANestedContainerBindingTravelsWithTheBody(): void
    {
        $body = Endpoint::post('/x')->xmlList([new Fixtures\Contact('Grace')], 'goods', 'good', ['inside' => 'good'])->body();

        self::assertInstanceOf(EndpointBody::class, $body);
        self::assertSame(['inside' => 'good'], $body->nestedContainers);
    }
}
